<?php
/**
 * Cookie Scanner - detects scripts and cookies on the site.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Cookie Scanner class.
 */
class MBR_CC_Cookie_Scanner {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Cookie_Scanner
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Cookie_Scanner
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor.
     */
    private function __construct() {
        // AJAX handler for scanning.
        add_action('wp_ajax_mbr_cc_scan_cookies', array($this, 'ajax_scan_cookies'));
    }
    
    /**
     * AJAX: Scan site for cookies and scripts.
     */
    public function ajax_scan_cookies() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $scan_type = isset($_POST['scan_type']) ? sanitize_text_field(wp_unslash($_POST['scan_type'])) : 'single';
        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : home_url();
        
        if ($scan_type === 'site-wide') {
            $offset  = isset($_POST['offset']) ? max(0, (int) wp_unslash($_POST['offset'])) : 0;
            $results = $this->scan_entire_site($offset);
        } else {
            $results = $this->scan_page($url);
        }
        
        if (is_wp_error($results)) {
            wp_send_json_error(array('message' => $results->get_error_message()));
        }
        
        wp_send_json_success($results);
    }
    
    /**
     * Check that a URL belongs to this site.
     *
     * On multisite the whole network is allowed, since a network admin
     * legitimately scans sibling sites.
     *
     * @param string $url URL to test.
     * @return bool
     */
    private function is_local_url($url) {
        if (!is_string($url) || $url === '') {
            return false;
        }
        
        $parts = wp_parse_url($url);
        
        if (empty($parts['host']) || empty($parts['scheme'])) {
            return false;
        }
        
        $scheme = strtolower($parts['scheme']);
        if (!in_array($scheme, array('http', 'https'), true)) {
            return false;
        }
        
        // Credentials in a URL have no business in a scan of your own pages,
        // and user@host forms are a classic way to confuse host checks.
        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        
        $host = strtolower($parts['host']);
        $port = isset($parts['port']) ? (int) $parts['port'] : self::default_port($scheme);
        
        // CHANGED IN 2.6.0: host AND port. Checking the hostname alone let
        // https://yoursite:8081/ through, which is a different service on the
        // same machine — an admin panel, a cache, a metrics endpoint — and the
        // reason a scanner restricted to "this site" exists in the first place.
        foreach ($this->allowed_origins() as $origin) {
            if ($origin['host'] !== $host) {
                continue;
            }
            // A site on the default port is reachable on either default port,
            // because http → https is the redirect every site issues.
            if (null === $origin['port']) {
                if (in_array($port, array(80, 443), true)) {
                    return true;
                }
            } elseif ($origin['port'] === $port) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Hosts (and explicit ports, if any) of this site and, on Multisite, the
     * network's sites.
     *
     * @return array[] Each array( 'host' => string, 'port' => int|null ).
     */
    private function allowed_origins() {
        $urls = array(home_url(), site_url());
        
        if (is_multisite()) {
            $urls[] = network_home_url();
            foreach (get_sites(array('number' => 200, 'fields' => 'ids')) as $site_id) {
                $urls[] = get_home_url($site_id);
            }
        }
        
        $origins = array();
        foreach ($urls as $u) {
            $p = wp_parse_url($u);
            if (empty($p['host'])) {
                continue;
            }
            $port = isset($p['port']) ? (int) $p['port'] : null;
            // An explicit default port is the same as none.
            if (in_array($port, array(80, 443), true)) {
                $port = null;
            }
            $key = strtolower($p['host']) . ':' . (null === $port ? '' : $port);
            $origins[$key] = array('host' => strtolower($p['host']), 'port' => $port);
        }
        
        return array_values($origins);
    }
    
    /**
     * @param string $scheme http or https.
     * @return int
     */
    private static function default_port($scheme) {
        return 'https' === $scheme ? 443 : 80;
    }
    
    /**
     * Maximum redirects followed when fetching a page to scan.
     */
    const MAX_REDIRECTS = 3;
    
    /**
     * Fetch one of this site's pages, following redirects only within it.
     *
     * Before 2.6.0 the URL was checked once and then fetched with
     * wp_remote_get(), which follows up to five redirects wherever they lead.
     * Any page on the site that redirects elsewhere — an open redirect in a
     * plugin, a short-link, a login flow — carried the request past the check.
     *
     * Redirects are now followed here, one hop at a time, and every hop is
     * checked against the same allowlist as the first. wp_safe_remote_get()
     * adds WordPress's own protection on each request: it refuses private and
     * loopback addresses for any host other than the site's own, and
     * non-standard ports.
     *
     * @param string $url Absolute URL, already validated.
     * @return array|WP_Error Response array or error.
     */
    private function fetch_local($url) {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            if (!$this->is_local_url($url)) {
                return new WP_Error(
                    'mbr_cc_external_url',
                    0 === $hop
                        ? __('The scanner can only scan pages on this site.', 'mbr-cookie-consent')
                        : __('The page redirected off this site, so the scan stopped there.', 'mbr-cookie-consent')
                );
            }
            
            $response = wp_safe_remote_get($url, array(
                'timeout'     => 10,
                'redirection' => 0,
            ));
            
            if (is_wp_error($response)) {
                return $response;
            }
            
            $code     = (int) wp_remote_retrieve_response_code($response);
            $location = wp_remote_retrieve_header($response, 'location');
            
            if ($code < 300 || $code >= 400 || '' === $location || null === $location) {
                return $response;
            }
            
            if (is_array($location)) {
                $location = end($location);
            }
            
            $url = WP_Http::make_absolute_url($location, $url);
        }
        
        return new WP_Error('mbr_cc_too_many_redirects', __('The page redirected too many times to scan.', 'mbr-cookie-consent'));
    }
    
    /**
     * Scan entire site (all published pages and posts).
     *
     * @return array Scan results organized by category.
     */
    /**
     * Scan the site in batches, resuming from an offset.
     *
     * The previous implementation fetched up to a thousand pages at a
     * thirty-second timeout apiece inside a single AJAX request. set_time_limit()
     * does not help — many hosts ignore it, and a web server or proxy will close
     * the connection long before PHP gives up — so on any site with real content
     * the scan simply died, usually with no useful message.
     *
     * Each call now works for a fixed wall-clock budget, stores what it has found
     * so far, and reports where it got to. The browser calls back with that
     * offset until the scan reports itself finished, so a large site takes
     * several short requests instead of one that cannot complete.
     *
     * @param int $offset Index in the URL list to resume from.
     * @return array Scan state, including 'done'.
     */
    public function scan_entire_site($offset = 0) {
        $started = microtime(true);

        /**
         * Seconds of wall clock per batch.
         *
         * Deliberately well inside the 30-second default that most hosts and
         * proxies enforce, so a batch returns cleanly rather than being cut off.
         */
        $budget = (float) apply_filters('mbr_cc_scan_time_budget', 12.0);

        /** Hard cap on pages per batch, so a site of fast pages still yields. */
        $batch_cap = (int) apply_filters('mbr_cc_scan_batch_size', 25);

        $urls      = $this->get_all_site_urls();
        $max_pages = (int) apply_filters('mbr_cc_scan_max_pages', 500);
        $urls      = array_slice($urls, 0, $max_pages);
        $total     = count($urls);

        // Findings so far are held in a transient between batches rather than
        // being sent back and forth through the browser.
        $progress_key = 'mbr_cc_scan_progress';
        $progress     = (0 === $offset) ? array('scripts' => array(), 'iframes' => array(), 'scanned' => 0)
                                        : get_transient($progress_key);

        if (!is_array($progress) || !isset($progress['scripts'])) {
            $progress = array('scripts' => array(), 'iframes' => array(), 'scanned' => 0);
            $offset   = 0;
        }

        $all_scripts   = $progress['scripts'];
        $all_iframes   = $progress['iframes'];
        $scanned_count = (int) $progress['scanned'];
        $index         = $offset;
        $in_batch      = 0;

        while ($index < $total && $in_batch < $batch_cap) {
            if ((microtime(true) - $started) >= $budget) {
                break;
            }

            $url          = $urls[$index];
            $page_results = $this->scan_page($url);
            $index++;
            $in_batch++;

            if (!is_wp_error($page_results)) {
                $scanned_count++;

                // Merge scripts (avoiding duplicates).
                foreach ($page_results['scripts'] as $script) {
                    $identifier = $script['identifier'];
                    if (!isset($all_scripts[$identifier])) {
                        $all_scripts[$identifier] = $script;
                        $all_scripts[$identifier]['found_on'] = array();
                    }
                    $all_scripts[$identifier]['found_on'][] = $url;
                }

                // Merge iframes (avoiding duplicates).
                foreach ($page_results['iframes'] as $iframe) {
                    $identifier = $iframe['identifier'];
                    if (!isset($all_iframes[$identifier])) {
                        $all_iframes[$identifier] = $iframe;
                        $all_iframes[$identifier]['found_on'] = array();
                    }
                    $all_iframes[$identifier]['found_on'][] = $url;
                }
            }
        }

        $done = ($index >= $total);

        if (!$done) {
            set_transient($progress_key, array(
                'scripts' => $all_scripts,
                'iframes' => $all_iframes,
                'scanned' => $scanned_count,
            ), HOUR_IN_SECONDS);

            return array(
                'done'          => false,
                'offset'        => $index,
                'pages_scanned' => $scanned_count,
                'total_urls'    => $total,
                'count'         => count($all_scripts) + count($all_iframes),
            );
        }

        delete_transient($progress_key);

        // Organize by category.
        $organized = $this->organize_by_category(array_values($all_scripts), array_values($all_iframes));
        
        return array(
            'done' => true,
            'scripts' => array_values($all_scripts),
            'iframes' => array_values($all_iframes),
            'by_category' => $organized,
            'count' => count($all_scripts) + count($all_iframes),
            'pages_scanned' => $scanned_count,
            'total_urls' => $total,
            'max_pages' => $max_pages,
        );
    }
    
    /**
     * Get all site URLs (pages and posts).
     *
     * @return array URLs.
     */
    private function get_all_site_urls() {
        $urls = array();
        
        // Get homepage.
        $urls[] = home_url();
        
        // Get all published pages.
        $pages = get_posts(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        foreach ($pages as $page) {
            $urls[] = get_permalink($page->ID);
        }
        
        // Get all published posts.
        $posts = get_posts(array(
            'post_type' => 'post',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC',
        ));
        
        foreach ($posts as $post) {
            $urls[] = get_permalink($post->ID);
        }
        
        // Remove duplicates.
        $urls = array_unique($urls);
        
        return $urls;
    }
    
    /**
     * Organize scripts and iframes by category.
     *
     * @param array $scripts Scripts.
     * @param array $iframes Iframes.
     * @return array Organized by category.
     */
    private function organize_by_category($scripts, $iframes) {
        $categories = array(
            'necessary' => array(),
            'analytics' => array(),
            'marketing' => array(),
            'preferences' => array(),
        );
        
        foreach ($scripts as $script) {
            $category = $script['category'];
            if (isset($categories[$category])) {
                $categories[$category][] = $script;
            }
        }
        
        foreach ($iframes as $iframe) {
            $category = $iframe['category'];
            if (isset($categories[$category])) {
                $categories[$category][] = $iframe;
            }
        }
        
        return $categories;
    }
    
    /**
     * Scan a page for scripts and iframes.
     *
     * @param string $url Page URL to scan.
     * @return array|WP_Error Scan results or error.
     */
    public function scan_page($url) {
        // The scanner exists to inspect this site's own pages. Fetching an
        // arbitrary URL would turn it into a request proxy able to reach hosts
        // the web server can see but the outside world cannot.
        if (!$this->is_local_url($url)) {
            return new WP_Error(
                'mbr_cc_external_url',
                __('The scanner can only scan pages on this site.', 'mbr-cookie-consent')
            );
        }
        
        // Fetch page content, following redirects only within this site.
        $response = $this->fetch_local($url);
        
        if (is_wp_error($response)) {
            return $response;
        }
        
        $html = wp_remote_retrieve_body($response);
        
        if (empty($html)) {
            return new WP_Error('empty_response', 'Failed to retrieve page content.');
        }
        
        // Parse HTML.
        $scripts = $this->extract_scripts($html);
        $iframes = $this->extract_iframes($html);
        
        return array(
            'scripts' => $scripts,
            'iframes' => $iframes,
        );
    }
    
    /**
     * Extract script tags from HTML.
     *
     * @param string $html Page HTML.
     * @return array Scripts found.
     */
    private function extract_scripts($html) {
        $scripts = array();
        
        // Match external scripts with a src attribute, in any valid form:
        // quoted either way, unquoted, and with or without spaces round "=".
        // The previous expression required src="…" exactly, so a script the
        // blocker could (now) hold was invisible to the scanner.
        preg_match_all(
            '/<script\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*?(?<![\w-])src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i',
            $html,
            $matches,
            PREG_SET_ORDER
        );
        
        $sources = array();
        foreach ($matches as $m) {
            $value = '' !== $m[1] ? $m[1] : ('' !== ($m[2] ?? '') ? $m[2] : ($m[3] ?? ''));
            if ('' !== $value) {
                $sources[] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }
        
        if (!empty($sources)) {
            foreach ($sources as $src) {
                $category = $this->categorize_script($src);
                
                $scripts[] = array(
                    'type' => 'src',
                    'identifier' => $src,
                    'name' => $this->get_script_name($src),
                    'category' => $category,
                    'description' => $this->get_script_description($src),
                );
            }
        }
        
        // Inline scripts with common tracking patterns.
        //
        // CHANGED IN 2.6.0. The identifier stored with an inline rule is what
        // the blocker searches script bodies for, literally. It used to be the
        // service's label — "facebook-pixel" — which appears in no script, so
        // the rule the scanner offered could never fire. And detection ran a
        // regex over the whole page, so "ga(" in any text at all counted.
        //
        // Now each service lists distinctive literals, in order of preference,
        // and the identifier is whichever one was actually found in an inline
        // script body. Short, ambiguous fragments such as "ga(" are gone: as a
        // literal rule it would also hold any script calling omega() or
        // setMega(), which is breakage the site owner would never trace back.
        // gtag( alone is avoided for the same reason — this plugin's own
        // Consent Mode default is a gtag('consent', …) call.
        $markers = array(
            'google-analytics'   => array("gtag('config'", 'gtag("config"', 'GoogleAnalyticsObject', "ga('create'", 'ga("create"'),
            'google-tag-manager' => array('googletagmanager.com/gtm.js'),
            'facebook-pixel'     => array("fbq('init'", 'fbq("init"', 'connect.facebook.net', 'facebook.com/tr'),
            'google-ads'         => array('googlesyndication.com', 'adsbygoogle'),
            'hotjar'             => array('static.hotjar.com', 'hotjar.com'),
        );
        
        preg_match_all(
            '/<script\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>((?:(?!<\/script>)[\s\S])*)<\/script>/i',
            $html,
            $inline,
            PREG_SET_ORDER
        );
        
        $bodies = array();
        foreach ($inline as $m) {
            // Only inline scripts: a body next to a src is ignored by browsers.
            if (preg_match('/(?<![\w-])src\s*=/i', $m[1])) {
                continue;
            }
            if ('' !== trim($m[2])) {
                $bodies[] = $m[2];
            }
        }
        
        foreach ($markers as $name => $literals) {
            foreach ($literals as $literal) {
                $found = false;
                foreach ($bodies as $body) {
                    // Exact match. The blocker matches case-insensitively, so
                    // any literal found here is guaranteed to be found there.
                    if (false !== strpos($body, $literal)) {
                        $found = true;
                        break;
                    }
                }
                
                if ($found) {
                    $scripts[] = array(
                        'type' => 'inline',
                        'identifier' => $literal,
                        'name' => $this->format_script_name($name),
                        'category' => $this->categorize_script($name),
                        'description' => '',
                    );
                    break;
                }
            }
        }
        
        return $scripts;
    }
    
    /**
     * Extract iframe tags from HTML.
     *
     * @param string $html Page HTML.
     * @return array Iframes found.
     */
    private function extract_iframes($html) {
        $iframes = array();
        
        preg_match_all(
            '/<iframe\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*?(?<![\w-])src\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+))/i',
            $html,
            $raw,
            PREG_SET_ORDER
        );
        $matches = array(1 => array());
        foreach ($raw as $m) {
            $value = '' !== $m[1] ? $m[1] : ('' !== ($m[2] ?? '') ? $m[2] : ($m[3] ?? ''));
            if ('' !== $value) {
                $matches[1][] = html_entity_decode($value, ENT_QUOTES, 'UTF-8');
            }
        }
        
        if (!empty($matches[1])) {
            foreach ($matches[1] as $src) {
                $category = $this->categorize_script($src);
                
                $iframes[] = array(
                    'type' => 'iframe',
                    'identifier' => $src,
                    'name' => $this->get_iframe_name($src),
                    'category' => $category,
                    'description' => '',
                );
            }
        }
        
        return $iframes;
    }
    
    /**
     * Categorize script based on source.
     *
     * @param string $src Script source.
     * @return string Category.
     */
    private function categorize_script($src) {
        $analytics_patterns = array(
            'google-analytics',
            'googletagmanager',
            'analytics',
            'ga.js',
            'gtag',
            'matomo',
            'piwik',
        );
        
        $marketing_patterns = array(
            'facebook',
            'fbq',
            'doubleclick',
            'googlesyndication',
            'adservice',
            'advertising',
            'pixel',
            'ads',
            'twitter',
            'linkedin',
            'tiktok',
        );
        
        $src_lower = strtolower($src);
        
        foreach ($analytics_patterns as $pattern) {
            if (strpos($src_lower, $pattern) !== false) {
                return 'analytics';
            }
        }
        
        foreach ($marketing_patterns as $pattern) {
            if (strpos($src_lower, $pattern) !== false) {
                return 'marketing';
            }
        }
        
        // Default to marketing for third-party scripts.
        $site_domain = wp_parse_url(home_url(), PHP_URL_HOST);
        $script_domain = wp_parse_url($src, PHP_URL_HOST);
        
        if ($script_domain && $script_domain !== $site_domain) {
            return 'marketing';
        }
        
        return 'preferences';
    }
    
    /**
     * Get friendly script name from source.
     *
     * @param string $src Script source.
     * @return string Friendly name.
     */
    private function get_script_name($src) {
        // Known services.
        $known = array(
            'google-analytics.com' => 'Google Analytics',
            'googletagmanager.com' => 'Google Tag Manager',
            'facebook.com' => 'Facebook Pixel',
            'facebook.net' => 'Facebook SDK',
            'doubleclick.net' => 'Google DoubleClick',
            'googlesyndication.com' => 'Google AdSense',
            'hotjar.com' => 'Hotjar',
            'twitter.com' => 'Twitter',
            'linkedin.com' => 'LinkedIn',
            'youtube.com' => 'YouTube',
            'vimeo.com' => 'Vimeo',
        );
        
        foreach ($known as $domain => $name) {
            if (strpos($src, $domain) !== false) {
                return $name;
            }
        }
        
        // Extract domain from URL.
        $parsed = wp_parse_url($src);
        if (isset($parsed['host'])) {
            return ucfirst(str_replace('www.', '', $parsed['host']));
        }
        
        return basename($src);
    }
    
    /**
     * Get iframe name from source.
     *
     * @param string $src Iframe source.
     * @return string Friendly name.
     */
    private function get_iframe_name($src) {
        if (strpos($src, 'youtube.com') !== false || strpos($src, 'youtu.be') !== false) {
            return 'YouTube Video';
        }
        
        if (strpos($src, 'vimeo.com') !== false) {
            return 'Vimeo Video';
        }
        
        if (strpos($src, 'google.com/maps') !== false) {
            return 'Google Maps';
        }
        
        $parsed = wp_parse_url($src);
        if (isset($parsed['host'])) {
            return ucfirst(str_replace('www.', '', $parsed['host'])) . ' Embed';
        }
        
        return 'External Embed';
    }
    
    /**
     * Format script name from identifier.
     *
     * @param string $identifier Script identifier.
     * @return string Formatted name.
     */
    private function format_script_name($identifier) {
        return ucwords(str_replace(array('-', '_'), ' ', $identifier));
    }
    
    /**
     * Get script description.
     *
     * @param string $src Script source.
     * @return string Description.
     */
    private function get_script_description($src) {
        $descriptions = array(
            'google-analytics' => 'Web analytics service that tracks and reports website traffic.',
            'googletagmanager' => 'Tag management system that allows you to manage marketing tags.',
            'facebook' => 'Tracks conversions from Facebook ads and builds audiences.',
            'doubleclick' => 'Ad serving platform for managing digital advertising campaigns.',
            'hotjar' => 'Behavior analytics tool that provides heatmaps and user recordings.',
        );
        
        foreach ($descriptions as $key => $description) {
            if (strpos($src, $key) !== false) {
                return $description;
            }
        }
        
        return '';
    }
}
