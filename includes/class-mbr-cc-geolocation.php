<?php
/**
 * Geolocation Detection Class
 * Detects user location and applies appropriate privacy regulations
 *
 * @package MBR_Cookie_Consent
 * @version 1.6.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_Geolocation {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * User's detected country code
     */
    private $country_code = null;
    
    /**
     * User's detected region (privacy law jurisdiction)
     */
    private $region = null;
    
    /**
     * EU/UK country codes (GDPR)
     */
    private $eu_uk_countries = array(
        // EU Countries
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        // UK (post-Brexit but maintains GDPR)
        'GB', 'UK'
    );
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        // Hook to detect location early
        add_action('init', array($this, 'detect_location'));
    }
    
    /**
     * Detect user's location
     */
    public function detect_location() {
        // Check if geolocation is enabled
        if (!get_option('mbr_cc_geolocation_enabled', false)) {
            $this->region = 'default';
            return;
        }
        
        // Check cache first
        $cached = $this->get_cached_location();
        if ($cached) {
            $this->country_code = $cached['country'];
            $this->region = $cached['region'];
            return;
        }
        
        // Get user IP
        $ip = $this->get_user_ip();
        
        // Detect country from IP
        $this->country_code = $this->detect_country_from_ip($ip);
        
        // Determine privacy region
        $this->region = $this->determine_region($this->country_code);
        
        // Cache the result
        $this->cache_location($this->country_code, $this->region);
    }
    
    /**
     * Get user's IP address
     */
    private function get_user_ip() {
        // Check for IP in various headers (proxy support)
        $ip_keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        );
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                // Validate IP
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
    }
    
    /**
     * Detect country from IP address
     */
    private function detect_country_from_ip($ip) {
        // Allow manual override for testing
        if (defined('MBR_CC_TEST_COUNTRY')) {
            return MBR_CC_TEST_COUNTRY;
        }
        
        // Skip for local/private IPs
        if (empty($ip) || 
            strpos($ip, '127.') === 0 || 
            strpos($ip, '192.168.') === 0 || 
            strpos($ip, '10.') === 0 ||
            $ip === '::1') {
            return $this->get_default_country();
        }
        
        // Get API provider
        $provider = get_option('mbr_cc_geolocation_provider', 'ip-api');
        
        $country = false;
        
        switch ($provider) {
            case 'ip-api':
                $country = $this->detect_via_ipapi($ip);
                break;
            case 'ipapi':
                $country = $this->detect_via_ipapi_com($ip);
                break;
            case 'cloudflare':
                $country = $this->detect_via_cloudflare();
                break;
            default:
                $country = $this->detect_via_ipapi($ip);
        }
        
        // Fallback to default if detection fails
        if (!$country) {
            $country = $this->get_default_country();
        }
        
        return strtoupper($country);
    }
    
    /**
     * Detect via ip-api.com (Free, 45 req/min)
     */
    private function detect_via_ipapi($ip) {
        $response = wp_remote_get("http://ip-api.com/json/{$ip}?fields=countryCode", array(
            'timeout' => 5,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        return isset($data['countryCode']) ? $data['countryCode'] : false;
    }
    
    /**
     * Detect via ipapi.co (Free, 1000 req/day)
     */
    private function detect_via_ipapi_com($ip) {
        $response = wp_remote_get("https://ipapi.co/{$ip}/country/", array(
            'timeout' => 5
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        $country = trim(wp_remote_retrieve_body($response));
        return !empty($country) && strlen($country) === 2 ? $country : false;
    }
    
    /**
     * Detect via Cloudflare headers
     */
    private function detect_via_cloudflare() {
        // Cloudflare adds CF-IPCountry header
        if (!empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return $_SERVER['HTTP_CF_IPCOUNTRY'];
        }
        return false;
    }
    
    /**
     * Determine privacy region from country code
     */
    private function determine_region($country_code) {
        // EU/UK - GDPR
        if (in_array($country_code, $this->eu_uk_countries)) {
            return 'eu_uk';
        }
        
        // California - CCPA (requires state detection)
        if ($country_code === 'US') {
            // For now, treat all US as potentially CCPA
            // Could be enhanced with state detection
            return 'ccpa';
        }
        
        // Brazil - LGPD
        if ($country_code === 'BR') {
            return 'lgpd';
        }
        
        // Canada - PIPEDA
        if ($country_code === 'CA') {
            return 'pipeda';
        }
        
        // Default for rest of world
        return 'default';
    }
    
    /**
     * Get default country if detection fails
     */
    private function get_default_country() {
        return get_option('mbr_cc_geolocation_default', 'US');
    }
    
    /**
     * Cache location data
     */
    private function cache_location($country, $region) {
        $ip = $this->get_user_ip();
        if (empty($ip)) {
            return;
        }
        
        $cache_duration = get_option('mbr_cc_geolocation_cache', 86400); // 24 hours default
        
        set_transient(
            'mbr_cc_geo_' . md5($ip),
            array(
                'country' => $country,
                'region' => $region,
                'timestamp' => time()
            ),
            $cache_duration
        );
    }
    
    /**
     * Get cached location data
     */
    private function get_cached_location() {
        $ip = $this->get_user_ip();
        if (empty($ip)) {
            return false;
        }
        
        return get_transient('mbr_cc_geo_' . md5($ip));
    }
    
    /**
     * Get detected country code
     */
    public function get_country() {
        if ($this->country_code === null) {
            $this->detect_location();
        }
        return $this->country_code;
    }
    
    /**
     * Get detected region
     */
    public function get_region() {
        if ($this->region === null) {
            $this->detect_location();
        }
        return $this->region;
    }
    
    /**
     * Check if user is in EU/UK
     */
    public function is_eu_uk() {
        return $this->get_region() === 'eu_uk';
    }
    
    /**
     * Check if user is in California/CCPA region
     */
    public function is_ccpa() {
        return $this->get_region() === 'ccpa';
    }
    
    /**
     * Check if user is in Brazil
     */
    public function is_lgpd() {
        return $this->get_region() === 'lgpd';
    }
    
    /**
     * Get region display name
     */
    public function get_region_name() {
        $names = array(
            'eu_uk' => 'EU/UK (GDPR)',
            'ccpa' => 'United States (CCPA)',
            'lgpd' => 'Brazil (LGPD)',
            'pipeda' => 'Canada (PIPEDA)',
            'default' => 'Rest of World'
        );
        
        $region = $this->get_region();
        return isset($names[$region]) ? $names[$region] : $names['default'];
    }
    
    /**
     * Clear location cache
     */
    public function clear_cache() {
        $ip = $this->get_user_ip();
        if (!empty($ip)) {
            delete_transient('mbr_cc_geo_' . md5($ip));
        }
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', function() {
    MBR_CC_Geolocation::get_instance();
}, 5);

// Helper function to get instance
function mbr_cc_geolocation() {
    return MBR_CC_Geolocation::get_instance();
}
