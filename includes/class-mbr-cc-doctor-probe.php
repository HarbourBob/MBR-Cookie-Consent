<?php
/**
 * Consent Doctor — live check.
 *
 * The browser tier. Everything in MBR_CC_Doctor reads settings or database
 * rows; this watches a real page load and reports the network requests it
 * actually made.
 *
 * The flow:
 *
 *   1. The Consent Doctor screen opens a hidden iframe pointing at the site's
 *      home page with a nonce-signed probe flag.
 *   2. maybe_enqueue_probe() sees the flag, checks the nonce and the
 *      capability, and loads doctor-probe.js on that page only.
 *   3. The probe reads Resource Timing, postMessages the hosts back.
 *   4. ajax_store_probe() classifies those hosts against the blocking rules and
 *      stores the finding.
 *
 * The probe reports the conditions it ran under — content blocker present,
 * consent cookie present — and those are surfaced rather than smoothed over. A
 * clean result from a browser with uBlock installed means nothing at all, and a
 * panel that showed it as a pass would be worse than no panel.
 *
 * @package MBR_Cookie_Consent
 * @since   2.4.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_Doctor_Probe {

    /** @var MBR_CC_Doctor_Probe|null */
    private static $instance = null;

    const FLAG        = 'mbr_cc_doctor_probe';
    const NONCE       = 'mbr_cc_doctor_probe';
    const RESULT_KEY  = 'mbr_cc_doctor_last_probe';

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_probe'), 1);
        add_action('wp_ajax_mbr_cc_doctor_probe_result', array($this, 'ajax_store_probe'));
        add_filter('script_loader_tag', array($this, 'exempt_probe_from_optimisers'), 99, 2);
    }

    /**
     * Keep the probe out of the hands of JavaScript optimisers.
     *
     * Performance plugins routinely defer or delay scripts until the visitor
     * interacts with the page — a scroll, a click, a keypress. The probe runs
     * inside an off-screen frame that nobody will ever interact with, so a
     * delayed probe simply never executes: the check hangs and then times out
     * with nothing to show for it.
     *
     * These attributes are the opt-out markers the common optimisers honour —
     * MBR Performance, SiteGround Speed Optimizer, WP Rocket, Autoptimize,
     * LiteSpeed, WP Fastest Cache and Cloudflare Rocket Loader. Marking the
     * probe is safe: it is only ever present on a nonce-signed diagnostic
     * request made by an administrator, never on a page a visitor sees.
     *
     * @param  string $tag    Script tag HTML.
     * @param  string $handle Script handle.
     * @return string
     */
    public function exempt_probe_from_optimisers($tag, $handle) {
        if ($handle !== 'mbr-cc-doctor-probe' && $handle !== 'mbr-cc-doctor-reset') {
            return $tag;
        }

        $markers = ' data-no-optimize="1" data-no-defer="1" data-no-minify="1"'
                 . ' data-cfasync="false" data-wpfc-render="false" data-pagespeed-no-defer';

        return str_replace('<script ', '<script' . $markers . ' ', $tag);
    }

    /** Is this request a probe load, by someone entitled to run one? */
    public function is_probe_request() {
        if (empty($_GET[self::FLAG])) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        $nonce = isset($_GET['_mbrnonce']) ? sanitize_text_field(wp_unslash($_GET['_mbrnonce'])) : '';

        return (bool) wp_verify_nonce($nonce, self::NONCE);
    }

    /**
     * Load the probe on a flagged front-end request.
     *
     * Priority 1 so it runs before the consent scripts. When asked to simulate
     * an unconsented visitor it clears the consent cookie for this document
     * before anything reads it, and puts it back once collection is done —
     * which is the only way to measure the pre-consent page without asking an
     * administrator to throw away a choice they may have made deliberately.
     */
    public function maybe_enqueue_probe() {
        if (!$this->is_probe_request()) {
            return;
        }

        $simulate = !empty($_GET['unconsented']);

        // Keep the probe page out of caches: a page built for a diagnostic must
        // never be served to a visitor, and a cached copy would report on the
        // wrong page anyway.
        nocache_headers();

        if ($simulate) {
            // Runs ahead of every consent script, so the page is built and
            // released exactly as it would be for a first-time visitor.
            wp_register_script('mbr-cc-doctor-reset', '', array(), MBR_CC_VERSION, false);
            wp_enqueue_script('mbr-cc-doctor-reset');
            wp_add_inline_script('mbr-cc-doctor-reset', $this->reset_consent_script(), 'after');
        }

        wp_enqueue_script(
            'mbr-cc-doctor-probe',
            MBR_CC_PLUGIN_URL . 'assets/js/doctor-probe.js',
            array(),
            MBR_CC_VERSION,
            true
        );

        // A marker the driver can look for. If the page loads and this is
        // absent, the probe was never enqueued — a redirect dropped the query
        // string, a cache served a copy without it, or a security layer
        // rewrote the request. That is a different fault from a script that
        // loaded and did not run, and it needs a different answer.
        // Priority 99, not 0. This method runs on wp_enqueue_scripts, which
        // WordPress fires from inside wp_head at priority 2 — so a callback
        // registered at priority 0 is already too late to run, and the marker
        // never appeared. The diagnosis then blamed a dropped query string on
        // every failure, whatever the real cause. Footer too, in case a theme
        // does something unusual with wp_head.
        $marker = function () {
            static $done = false;

            if ($done) {
                return;
            }

            $done = true;
            echo '<meta name="mbr-cc-doctor-probe" content="1">' . "\n";
        };

        add_action('wp_head', $marker, 99);
        add_action('wp_footer', $marker, 99);

        wp_localize_script('mbr-cc-doctor-probe', 'mbrCcDoctorProbe', array(
            'simulateUnconsented' => (bool) $simulate,
            // Served by this site, but named so filter lists match it. If this
            // request fails, something in the browser is intercepting.
            'baitUrl'             => MBR_CC_PLUGIN_URL . 'assets/js/ads/advert-banner.js',
        ));
    }

    /**
     * Inline script: stash and clear the consent cookie, restore it afterwards.
     *
     * Deliberately not a file: it must run before anything else on the page,
     * and it is short enough to read in full here.
     */
    private function reset_consent_script() {
        return '
(function(){
    try {
        var m = document.cookie.match(/(?:^|;\s*)mbr_cc_consent=([^;]*)/);
        var saved = m ? m[1] : null;
        if (saved !== null) {
            // Clear for this document so the consent layer treats the load as
            // a first visit. Put it back shortly afterwards so the administrator
            // does not silently lose their own choice on the real site.
            document.cookie = "mbr_cc_consent=; Max-Age=0; path=/";
            window.setTimeout(function(){
                document.cookie = "mbr_cc_consent=" + saved + "; path=/; SameSite=Lax" +
                    (location.protocol === "https:" ? "; Secure" : "");
            }, 6000);
        }
    } catch (e) {}
})();';
    }

    /** Receive, classify and store a probe result. */
    public function ajax_store_probe() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'mbr-cookie-consent')));
        }

        $raw = isset($_POST['result']) ? wp_unslash($_POST['result']) : '';
        $data = json_decode(is_string($raw) ? $raw : '', true);

        if (!is_array($data)) {
            wp_send_json_error(array('message' => __('The probe returned nothing readable.', 'mbr-cookie-consent')));
        }

        $requests = isset($data['requests']) && is_array($data['requests']) ? $data['requests'] : array();
        $classified = array();

        foreach ($requests as $req) {
            $host = isset($req['host']) ? strtolower(sanitize_text_field($req['host'])) : '';

            if ($host === '') {
                continue;
            }

            $match = $this->classify($host);

            $classified[] = array(
                'host'      => $host,
                'count'     => isset($req['count']) ? absint($req['count']) : 1,
                'initiator' => isset($req['initiator']) ? sanitize_text_field($req['initiator']) : '',
                'service'   => $match['service'],
                'category'  => $match['category'],
                'known'     => $match['known'],
            );
        }

        $consent = isset($data['consent']) && is_array($data['consent']) ? $data['consent'] : array();
        $blocker = isset($data['blocker']) && is_array($data['blocker']) ? $data['blocker'] : array();

        $result = array(
            'time'          => time(),
            'requests'      => $classified,
            'consented'     => !empty($consent['present']),
            'simulated'     => !empty($data['simulatedUnconsented']),
            'blocker'       => (!empty($blocker['baitHidden']) || !empty($blocker['fetchBlocked'])),
            'frames'        => isset($data['frames']) ? absint($data['frames']) : 0,
            'url'           => isset($data['url']) ? esc_url_raw($data['url']) : '',
            'target'        => isset($_POST['target']) ? self::sanitise_target(wp_unslash($_POST['target'])) : '',
        );

        update_option(self::RESULT_KEY, $result, false);

        wp_send_json_success($result);
    }

    /**
     * Map a hostname onto a known service, if any.
     *
     * Matches against the same rules the blocker uses, so a host reported as
     * unclassified here is genuinely one the plugin would not have held.
     *
     * @param  string $host Hostname.
     * @return array{service:string,category:string,known:bool}
     */
    private function classify($host) {
        if (class_exists('MBR_CC_Script_Blocker')) {
            foreach ((array) MBR_CC_Script_Blocker::get_builtin_services() as $category => $services) {
                foreach ((array) $services as $service) {
                    foreach ((array) $service['domains'] as $domain) {
                        $needle = strtolower(wp_parse_url('http://' . ltrim($domain, '/'), PHP_URL_HOST));
                        $needle = $needle ? $needle : strtolower($domain);

                        if ($host === $needle || substr($host, -strlen('.' . $needle)) === '.' . $needle) {
                            return array(
                                'service'  => $service['name'],
                                'category' => $category,
                                'known'    => true,
                            );
                        }
                    }
                }
            }
        }

        foreach ((array) get_option('mbr_cc_blocked_scripts', array()) as $script) {
            foreach ((array) $script as $key => $value) {
                if (!is_string($value) || $value === '' || $key === 'name' || $key === 'category') {
                    continue;
                }

                if (strpos($host, strtolower(wp_parse_url('http://' . ltrim($value, '/'), PHP_URL_HOST))) !== false) {
                    return array(
                        'service'  => isset($script['name']) ? $script['name'] : $value,
                        'category' => isset($script['category']) ? $script['category'] : 'unknown',
                        'known'    => true,
                    );
                }
            }
        }

        return array('service' => '', 'category' => '', 'known' => false);
    }

    /** @return array|false Last stored probe result. */
    public static function last_result() {
        $r = get_option(self::RESULT_KEY, false);

        return is_array($r) ? $r : false;
    }

    /**
     * URL to load in the probe iframe.
     *
     * @param bool   $simulate Clear consent for the probe load.
     * @param string $target   Page to check. Defaults to the home page.
     * @return string
     */
    public static function probe_url($simulate = true, $target = '') {
        $args = array(
            self::FLAG   => 1,
            '_mbrnonce'  => wp_create_nonce(self::NONCE),
        );

        if ($simulate) {
            $args['unconsented'] = 1;
        }

        return add_query_arg($args, self::sanitise_target($target));
    }

    /**
     * Confine the check to this site.
     *
     * The target comes from a field an administrator types into, and the probe
     * loads it in a frame that runs our script and posts results back. A URL on
     * somebody else's host would load a page we have no business instrumenting,
     * and its results would be meaningless anyway — the blocking rules being
     * tested are not in force there.
     *
     * The postMessage origin check would reject a foreign result regardless, so
     * this is the second of two independent guards rather than the only one.
     *
     * @param  string $target Candidate URL.
     * @return string A URL on this site.
     */
    public static function sanitise_target($target) {
        $home = home_url('/');

        if (!is_string($target) || trim($target) === '') {
            return $home;
        }

        $target = trim($target);

        // A path typed by hand, or pasted from the address bar without the
        // host. Resolve it rather than silently substituting the home page,
        // which would report on a page the administrator did not ask about.
        if (strpos($target, '/') === 0 && strpos($target, '//') !== 0) {
            $target = home_url($target);
        }

        $target = esc_url_raw($target);

        if ($target === '') {
            return $home;
        }

        $target_host = strtolower((string) wp_parse_url($target, PHP_URL_HOST));
        $home_host   = strtolower((string) wp_parse_url($home, PHP_URL_HOST));

        if ($target_host === '' || $target_host !== $home_host) {
            return $home;
        }

        return $target;
    }

    /**
     * A few pages worth checking, beyond the home page.
     *
     * The home page is the least likely to leak: embeds and comment threads —
     * which is where Gravatar and video players live — are usually on inner
     * pages. Offering them by name makes the difference between one datapoint
     * and something you can actually sweep.
     *
     * @return array List of array{label:string,url:string}.
     */
    public static function suggested_targets() {
        $out = array(array('label' => __('Home page', 'mbr-cookie-consent'), 'url' => home_url('/')));
        $seen = array(home_url('/') => true);

        $add = function ($url, $label) use (&$out, &$seen) {
            if ($url === '' || isset($seen[$url]) || count($out) >= 12) {
                return;
            }

            $seen[$url] = true;
            $out[] = array('label' => $label, 'url' => $url);
        };

        // Posts and pages carrying comments: where avatars appear.
        $commented = get_posts(array(
            'numberposts'      => 4,
            'post_type'        => array('post', 'page'),
            'post_status'      => 'publish',
            'orderby'          => 'comment_count',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ));

        foreach ($commented as $post) {
            if ((int) $post->comment_count > 0) {
                $add(get_permalink($post), sprintf(
                    /* translators: 1: title, 2: number of comments. */
                    _n('%1$s (%2$d comment)', '%1$s (%2$d comments)', (int) $post->comment_count, 'mbr-cookie-consent'),
                    wp_trim_words(get_the_title($post), 6, '…'),
                    (int) $post->comment_count
                ));
            }
        }

        // Anything whose content holds an embed — the other place tracking
        // escapes. A site built entirely of pages, with no posts and no
        // comments, would otherwise be offered nothing but its home page.
        $embeds = get_posts(array(
            'numberposts'      => 4,
            'post_type'        => array('post', 'page'),
            'post_status'      => 'publish',
            'suppress_filters' => false,
            's'                => 'youtube',
        ));

        foreach ($embeds as $post) {
            $add(get_permalink($post), sprintf(
                /* translators: %s: post title. */
                __('%s (mentions YouTube)', 'mbr-cookie-consent'),
                wp_trim_words(get_the_title($post), 6, '…')
            ));
        }

        // Then simply the most recent pages and posts.
        $recent = get_posts(array(
            'numberposts'      => 8,
            'post_type'        => array('page', 'post'),
            'post_status'      => 'publish',
            'orderby'          => 'modified',
            'order'            => 'DESC',
            'suppress_filters' => false,
        ));

        foreach ($recent as $post) {
            $add(get_permalink($post), wp_trim_words(get_the_title($post), 8, '…'));
        }

        return $out;
    }
}
