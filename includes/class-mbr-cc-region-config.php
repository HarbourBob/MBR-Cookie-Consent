<?php
/**
 * Region-Specific Banner Configuration
 * Adjusts banner behavior based on detected region
 *
 * @package MBR_Cookie_Consent
 * @version 1.6.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_Region_Config {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Geolocation instance
     */
    private $geo;
    
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
        $this->geo = mbr_cc_geolocation();
        
        // Filter banner configuration based on region
        add_filter('mbr_cc_banner_config', array($this, 'apply_region_config'));
    }
    
    /**
     * Apply region-specific configuration
     */
    public function apply_region_config($config) {
        if (!get_option('mbr_cc_geolocation_enabled', false)) {
            return $config;
        }
        
        $region = $this->geo->get_region();
        
        // Get region-specific overrides
        $method = "get_{$region}_config";
        if (method_exists($this, $method)) {
            $region_config = $this->$method();
            $config = array_merge($config, $region_config);
        }
        
        return $config;
    }
    
    /**
     * EU/UK (GDPR) Configuration
     */
    private function get_eu_uk_config() {
        return array(
            // GDPR requires explicit consent
            'require_consent' => true,
            
            // Reject button must be equally prominent
            'show_reject_button' => true,
            'reject_button_prominence' => 'equal',
            
            // Show customize/preferences button
            'show_customize_button' => true,
            
            // Don't auto-accept on scroll/click
            'auto_accept_on_scroll' => false,
            'auto_accept_on_click' => false,
            
            // Show all cookie categories
            'show_categories' => true,
            
            // GDPR-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_eu_uk_heading', 'We value your privacy'),
            'banner_description' => get_option('mbr_cc_geolocation_eu_uk_description', 
                'We use cookies to enhance your experience. By clicking "Accept", you consent to our use of cookies. You can manage your preferences or reject non-essential cookies.'
            ),
            
            // Enable CCPA link? (No for EU)
            'enable_ccpa' => false,
        );
    }
    
    /**
     * CCPA (California/US) Configuration
     */
    private function get_ccpa_config() {
        return array(
            // CCPA is opt-out based
            'require_consent' => false,
            
            // Can use implied consent
            'auto_accept_on_scroll' => get_option('mbr_cc_ccpa_auto_accept', false),
            
            // Show "Do Not Sell" link prominently
            'enable_ccpa' => true,
            'ccpa_link_text' => get_option('mbr_cc_ccpa_link_text', 'Do Not Sell or Share My Personal Information'),
            
            // Reject button optional
            'show_reject_button' => get_option('mbr_cc_ccpa_show_reject', true),
            
            // CCPA-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_ccpa_heading', 'Your Privacy Rights'),
            'banner_description' => get_option('mbr_cc_geolocation_ccpa_description',
                'We use cookies and similar technologies. California residents can opt out of the "sale" of personal information by clicking "Do Not Sell or Share My Personal Information".'
            ),
        );
    }
    
    /**
     * LGPD (Brazil) Configuration
     */
    private function get_lgpd_config() {
        return array(
            // LGPD similar to GDPR
            'require_consent' => true,
            
            // Equal reject button
            'show_reject_button' => true,
            'reject_button_prominence' => 'equal',
            
            // Show customize button
            'show_customize_button' => true,
            
            // No auto-accept
            'auto_accept_on_scroll' => false,
            'auto_accept_on_click' => false,
            
            // LGPD-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_lgpd_heading', 'Nós valorizamos sua privacidade'),
            'banner_description' => get_option('mbr_cc_geolocation_lgpd_description',
                'Usamos cookies para melhorar sua experiência. Ao clicar em "Aceitar", você concorda com o uso de cookies.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * PIPEDA (Canada) Configuration
     */
    private function get_pipeda_config() {
        return array(
            // PIPEDA requires meaningful consent
            'require_consent' => true,
            
            // Show reject button
            'show_reject_button' => true,
            
            // Show customize button
            'show_customize_button' => true,
            
            // Canada-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_pipeda_heading', 'Your Privacy Matters'),
            'banner_description' => get_option('mbr_cc_geolocation_pipeda_description',
                'We use cookies to enhance your browsing experience. You can accept, reject, or customize your cookie preferences.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * Default (Rest of World) Configuration
     */
    private function get_default_config() {
        return array(
            // Lenient for other regions
            'require_consent' => get_option('mbr_cc_geolocation_default_require', false),
            
            // Can use implied consent
            'auto_accept_on_scroll' => get_option('mbr_cc_default_auto_accept', true),
            
            // Simpler banner
            'show_reject_button' => get_option('mbr_cc_default_show_reject', false),
            'show_customize_button' => get_option('mbr_cc_default_show_customize', true),
            
            // Default text
            'banner_heading' => get_option('mbr_cc_banner_heading', 'We use cookies'),
            'banner_description' => get_option('mbr_cc_banner_description',
                'We use cookies to enhance your browsing experience. By continuing to use this site, you accept our use of cookies.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * Get current region configuration
     */
    public function get_current_config() {
        $config = array();
        $region = $this->geo->get_region();
        
        $method = "get_{$region}_config";
        if (method_exists($this, $method)) {
            $config = $this->$method();
        } else {
            $config = $this->get_default_config();
        }
        
        return $config;
    }
    
    /**
     * Get region compliance info
     */
    public function get_compliance_info($region = null) {
        if ($region === null) {
            $region = $this->geo->get_region();
        }
        
        $info = array(
            'eu_uk' => array(
                'name' => 'EU/UK GDPR',
                'law' => 'General Data Protection Regulation',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Explicit opt-in consent required',
                    'Reject button must be equally prominent',
                    'Pre-ticked boxes not allowed',
                    'Cookie categories must be shown',
                    'Withdrawal of consent must be easy'
                ),
                'penalties' => 'Up to €20 million or 4% of annual turnover'
            ),
            'ccpa' => array(
                'name' => 'California CCPA',
                'law' => 'California Consumer Privacy Act',
                'requires_consent' => false,
                'key_requirements' => array(
                    'Must provide "Do Not Sell" link',
                    'Opt-out based (not opt-in)',
                    'Must honor opt-out requests',
                    'Must disclose data collection practices',
                    'Users can request data deletion'
                ),
                'penalties' => 'Up to $7,500 per violation'
            ),
            'lgpd' => array(
                'name' => 'Brazil LGPD',
                'law' => 'Lei Geral de Proteção de Dados',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Clear and specific consent required',
                    'Must show legitimate purpose',
                    'Users can revoke consent',
                    'Data minimization required',
                    'Similar to GDPR requirements'
                ),
                'penalties' => 'Up to 2% of revenue (max R$50 million)'
            ),
            'pipeda' => array(
                'name' => 'Canada PIPEDA',
                'law' => 'Personal Information Protection Act',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Meaningful consent required',
                    'Must identify purpose before collection',
                    'Implied consent allowed in some cases',
                    'Users can withdraw consent',
                    'Privacy policies must be clear'
                ),
                'penalties' => 'Various provincial penalties apply'
            ),
            'default' => array(
                'name' => 'General Best Practices',
                'law' => 'No specific regulation',
                'requires_consent' => false,
                'key_requirements' => array(
                    'Transparent about cookie usage',
                    'Provide privacy policy',
                    'Allow users to manage preferences',
                    'Respect user choices'
                ),
                'penalties' => 'Varies by jurisdiction'
            )
        );
        
        return isset($info[$region]) ? $info[$region] : $info['default'];
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', function() {
    MBR_CC_Region_Config::get_instance();
}, 10);

// Helper function to get instance
function mbr_cc_region_config() {
    return MBR_CC_Region_Config::get_instance();
}
