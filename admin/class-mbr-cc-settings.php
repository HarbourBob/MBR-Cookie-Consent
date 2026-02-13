<?php
/**
 * Settings handler.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Settings class.
 */
class MBR_CC_Settings {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Settings
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Settings
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
        add_action('admin_init', array($this, 'register_settings'));
        add_action('wp_ajax_mbr_cc_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_mbr_cc_add_blocked_script', array($this, 'ajax_add_blocked_script'));
        add_action('wp_ajax_mbr_cc_remove_blocked_script', array($this, 'ajax_remove_blocked_script'));
        add_action('wp_ajax_mbr_cc_update_categories', array($this, 'ajax_update_categories'));
    }
    
    /**
     * Register plugin settings.
     */
    public function register_settings() {
        // Banner settings.
        register_setting('mbr_cc_settings', 'mbr_cc_banner_position');
        register_setting('mbr_cc_settings', 'mbr_cc_banner_layout');
        register_setting('mbr_cc_settings', 'mbr_cc_primary_color');
        register_setting('mbr_cc_settings', 'mbr_cc_accept_button_color');
        register_setting('mbr_cc_settings', 'mbr_cc_reject_button_color');
        register_setting('mbr_cc_settings', 'mbr_cc_text_color');
        register_setting('mbr_cc_settings', 'mbr_cc_revisit_button_text_color');
        register_setting('mbr_cc_settings', 'mbr_cc_show_reject_button');
        register_setting('mbr_cc_settings', 'mbr_cc_show_customize_button');
        register_setting('mbr_cc_settings', 'mbr_cc_show_close_button');
        register_setting('mbr_cc_settings', 'mbr_cc_reload_on_consent');
        
        // Text settings.
        register_setting('mbr_cc_settings', 'mbr_cc_banner_heading');
        register_setting('mbr_cc_settings', 'mbr_cc_banner_description');
        register_setting('mbr_cc_settings', 'mbr_cc_accept_button_text');
        register_setting('mbr_cc_settings', 'mbr_cc_reject_button_text');
        register_setting('mbr_cc_settings', 'mbr_cc_customize_button_text');
        
        // Cookie settings.
        register_setting('mbr_cc_settings', 'mbr_cc_cookie_expiry_days');
        
        // CCPA settings.
        register_setting('mbr_cc_settings', 'mbr_cc_enable_ccpa');
        register_setting('mbr_cc_settings', 'mbr_cc_ccpa_link_text');
        
        // Revisit consent.
        register_setting('mbr_cc_settings', 'mbr_cc_revisit_consent_enabled');
        register_setting('mbr_cc_settings', 'mbr_cc_revisit_consent_text');
        
        // Policy links.
        register_setting('mbr_cc_settings', 'mbr_cc_show_privacy_policy_link');
        register_setting('mbr_cc_settings', 'mbr_cc_privacy_policy_url');
        register_setting('mbr_cc_settings', 'mbr_cc_privacy_policy_text');
        register_setting('mbr_cc_settings', 'mbr_cc_show_cookie_policy_link');
        register_setting('mbr_cc_settings', 'mbr_cc_cookie_policy_url');
        register_setting('mbr_cc_settings', 'mbr_cc_cookie_policy_text');
        
        // Branding.
        register_setting('mbr_cc_settings', 'mbr_cc_banner_logo_url');
        
        // Google Consent Mode v2.
        register_setting('mbr_cc_settings', 'mbr_cc_google_consent_mode');
        register_setting('mbr_cc_settings', 'mbr_cc_google_default_deny');
        register_setting('mbr_cc_settings', 'mbr_cc_google_ads_redaction');
        register_setting('mbr_cc_settings', 'mbr_cc_google_url_passthrough');
        
        // Microsoft UET Consent Mode.
        register_setting('mbr_cc_settings', 'mbr_cc_microsoft_consent_mode');
        register_setting('mbr_cc_settings', 'mbr_cc_microsoft_default_deny');
        
        // Internationalization & Accessibility.
        register_setting('mbr_cc_settings', 'mbr_cc_auto_translate');
        register_setting('mbr_cc_settings', 'mbr_cc_wcag_compliance');
        
        // Page-Specific Controls.
        register_setting('mbr_cc_settings', 'mbr_cc_excluded_pages');
        register_setting('mbr_cc_settings', 'mbr_cc_excluded_url_patterns');
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_login');
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_checkout');
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_cart');
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_account');
        
        // Custom CSS.
        register_setting('mbr_cc_settings', 'mbr_cc_custom_css');
        
        // Subdomain Consent.
        register_setting('mbr_cc_settings', 'mbr_cc_subdomain_sharing');
        register_setting('mbr_cc_settings', 'mbr_cc_subdomain_root_domain');
        
        // IAB TCF v2.3.
        register_setting('mbr_cc_settings', 'mbr_cc_iab_tcf_enabled');
        register_setting('mbr_cc_settings', 'mbr_cc_publisher_country_code');
        register_setting('mbr_cc_settings', 'mbr_cc_purpose_one_treatment');
        register_setting('mbr_cc_settings', 'mbr_cc_gdpr_applies');
        
        // Google ACM.
        register_setting('mbr_cc_settings', 'mbr_cc_google_acm_enabled');
    }
    
    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $settings = isset($_POST['settings']) ? $_POST['settings'] : array();
        
        if (empty($settings)) {
            wp_send_json_error(array('message' => 'No settings provided.'));
        }
        
        foreach ($settings as $key => $value) {
            $option_key = 'mbr_cc_' . $key;
            
            // Sanitize based on type.
            if (is_bool($value) || $value === 'true' || $value === 'false') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($value)) {
                $value = intval($value);
            } else {
                $value = sanitize_text_field($value);
            }
            
            update_option($option_key, $value);
        }
        
        wp_send_json_success(array('message' => 'Settings saved successfully.'));
    }
    
    /**
     * AJAX: Add blocked script.
     */
    public function ajax_add_blocked_script() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $script = array(
            'name' => isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '',
            'identifier' => isset($_POST['identifier']) ? sanitize_text_field($_POST['identifier']) : '',
            'type' => isset($_POST['type']) ? sanitize_text_field($_POST['type']) : 'src',
            'category' => isset($_POST['category']) ? sanitize_text_field($_POST['category']) : 'marketing',
            'description' => isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '',
        );
        
        if (empty($script['name']) || empty($script['identifier'])) {
            wp_send_json_error(array('message' => 'Name and identifier are required.'));
        }
        
        $blocker = MBR_CC_Script_Blocker::get_instance();
        $result = $blocker->add_blocked_script($script);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Script added successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to add script.'));
        }
    }
    
    /**
     * AJAX: Remove blocked script.
     */
    public function ajax_remove_blocked_script() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $index = isset($_POST['index']) ? intval($_POST['index']) : -1;
        
        if ($index < 0) {
            wp_send_json_error(array('message' => 'Invalid index.'));
        }
        
        $blocker = MBR_CC_Script_Blocker::get_instance();
        $result = $blocker->remove_blocked_script($index);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Script removed successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to remove script.'));
        }
    }
    
    /**
     * AJAX: Update cookie categories.
     */
    public function ajax_update_categories() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $categories = isset($_POST['categories']) ? $_POST['categories'] : array();
        
        if (empty($categories)) {
            wp_send_json_error(array('message' => 'No categories provided.'));
        }
        
        // Sanitize categories.
        $sanitized = array();
        foreach ($categories as $slug => $category) {
            $sanitized[sanitize_key($slug)] = array(
                'name' => sanitize_text_field($category['name']),
                'description' => sanitize_textarea_field($category['description']),
                'required' => isset($category['required']) && filter_var($category['required'], FILTER_VALIDATE_BOOLEAN),
                'enabled' => isset($category['enabled']) && filter_var($category['enabled'], FILTER_VALIDATE_BOOLEAN),
            );
        }
        
        $manager = MBR_CC_Consent_Manager::get_instance();
        $result = $manager->update_categories($sanitized);
        
        if ($result) {
            wp_send_json_success(array('message' => 'Categories updated successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update categories.'));
        }
    }
}
