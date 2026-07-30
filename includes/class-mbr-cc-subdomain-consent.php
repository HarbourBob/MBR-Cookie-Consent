<?php
/**
 * Subdomain Consent Sharing
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Subdomain Consent class.
 */
class MBR_CC_Subdomain_Consent {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Subdomain_Consent
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Subdomain_Consent
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
        // Modify cookie domain if subdomain sharing is enabled.
        add_filter('mbr_cc_cookie_domain', array($this, 'get_cookie_domain'));
        add_filter('mbr_cc_cookie_path', array($this, 'get_cookie_path'));
    }
    
    /**
     * Get cookie domain for subdomain sharing.
     *
     * @param string $domain Default domain.
     * @return string Cookie domain.
     */
    public function get_cookie_domain($domain) {
        if (!get_option('mbr_cc_subdomain_sharing', false)) {
            return $domain;
        }
        
        // Get root domain for subdomain sharing.
        $root_domain = $this->get_root_domain();
        
        if (!empty($root_domain)) {
            // Return .example.com format for subdomain sharing.
            return '.' . $root_domain;
        }
        
        return $domain;
    }
    
    /**
     * Get cookie path.
     *
     * @param string $path Default path.
     * @return string Cookie path.
     */
    public function get_cookie_path($path) {
        // Always use / for subdomain sharing.
        if (get_option('mbr_cc_subdomain_sharing', false)) {
            return '/';
        }
        
        return $path;
    }
    
    /**
     * Get root domain from current URL.
     *
     * @return string Root domain.
     */
    private function get_root_domain() {
        // Allow manual override.
        $manual_domain = get_option('mbr_cc_subdomain_root_domain', '');
        if (!empty($manual_domain)) {
            return ltrim(trim($manual_domain), '.');
        }
        
        // Derive from the site's configured address rather than HTTP_HOST.
        // HTTP_HOST is client-supplied, and a forged Host on a request that
        // primes the page cache would bake a bad cookie domain into cached
        // HTML for every subsequent visitor.
        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        
        if ($host === '') {
            return '';
        }
        
        // Remove www if present.
        $host = preg_replace('/^www\./', '', $host);
        
        // For localhost or IP, return as-is.
        if (filter_var($host, FILTER_VALIDATE_IP) || strpos($host, '.') === false) {
            return $host;
        }
        
        $parts = explode('.', $host);
        $count = count($parts);
        
        if ($count <= 2) {
            return $host;
        }
        
        // Taking the last two labels is wrong wherever registrations happen at
        // the third level: shop.example.co.uk would yield "co.uk", and browsers
        // reject a cookie scoped to a public suffix, so subdomain sharing
        // silently failed across the whole .co.uk space (and .com.au, .co.nz,
        // and the rest).
        //
        // A full Public Suffix List is too heavy to ship and stale the moment
        // it is bundled, so the common second-level registry patterns are
        // matched instead. Anything unusual can be pinned exactly with the
        // manual override above.
        $last_two = $parts[$count - 2] . '.' . $parts[$count - 1];
        
        if ($this->is_multi_part_suffix($last_two) && $count >= 3) {
            return $parts[$count - 3] . '.' . $last_two;
        }
        
        return $last_two;
    }
    
    /**
     * Check whether the final two labels of a host form a public suffix under
     * which registrations are made at the third level.
     *
     * @param string $suffix Final two labels, e.g. 'co.uk'.
     * @return bool
     */
    private function is_multi_part_suffix($suffix) {
        $second_level = array(
            'co', 'com', 'net', 'org', 'gov', 'edu', 'ac', 'mil', 'sch',
            'ltd', 'plc', 'me', 'or', 'ne', 'go', 'in', 'nom', 'firm',
            'gen', 'kyoto', 'web', 'info', 'biz', 'asn', 'id', 'priv',
        );
        
        $parts = explode('.', $suffix);
        
        if (count($parts) !== 2) {
            return false;
        }
        
        // Only applies where the final label is a two-letter ccTLD; gTLDs such
        // as .com and .org register at the second level.
        if (strlen($parts[1]) !== 2) {
            return false;
        }
        
        $matches = in_array($parts[0], $second_level, true);
        
        /**
         * Filter whether a two-label suffix is treated as a public suffix.
         *
         * @since 2.3.1
         *
         * @param bool   $matches Whether registrations occur at the third level.
         * @param string $suffix  The two-label suffix under test.
         */
        return (bool) apply_filters('mbr_cc_is_multi_part_suffix', $matches, $suffix);
    }
    
    /**
     * Get cookie domain display.
     *
     * @return string Display domain.
     */
    public static function get_cookie_domain_display() {
        $root = self::get_instance()->get_root_domain();
        return '.' . $root;
    }
    
    /**
     * Get example subdomains.
     *
     * @return array Example subdomains.
     */
    public static function get_example_subdomains() {
        $root = self::get_instance()->get_root_domain();
        
        return array(
            'www.' . $root,
            'shop.' . $root,
            'blog.' . $root,
            'app.' . $root,
        );
    }
    
    /**
     * Test subdomain consent sharing.
     *
     * @return array Test results.
     */
    public static function test_subdomain_sharing() {
        $enabled = get_option('mbr_cc_subdomain_sharing', false);
        
        if (!$enabled) {
            return array(
                'enabled' => false,
                'message' => 'Subdomain sharing is not enabled.',
            );
        }
        
        $domain = self::get_cookie_domain_display();
        $current_host = $_SERVER['HTTP_HOST'];
        
        return array(
            'enabled' => true,
            'cookie_domain' => $domain,
            'current_host' => $current_host,
            'message' => sprintf(
                'Consent cookies will be shared across all subdomains of %s',
                $domain
            ),
        );
    }
}
