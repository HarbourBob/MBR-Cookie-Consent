<?php
/**
 * Geolocation AJAX Handlers
 *
 * @package MBR_Cookie_Consent
 * @version 1.6.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test geolocation for a specific country
 */
function mbr_cc_ajax_test_geolocation() {
    check_ajax_referer('mbr_cc_geo_test', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $country = isset($_POST['country']) ? strtoupper(sanitize_text_field($_POST['country'])) : '';
    
    if (strlen($country) !== 2) {
        wp_send_json_error('Invalid country code');
    }
    
    // Determine region directly
    $eu_uk = array('AT','BE','BG','HR','CY','CZ','DK','EE','FI','FR','DE','GR','HU','IE','IT','LV','LT','LU','MT','NL','PL','PT','RO','SK','SI','ES','SE','GB','UK');
    
    if (in_array($country, $eu_uk)) {
        $region = 'eu_uk';
        $region_name = 'EU/UK (GDPR)';
    } elseif ($country === 'US') {
        $region = 'ccpa';
        $region_name = 'United States (CCPA)';
    } elseif ($country === 'BR') {
        $region = 'lgpd';
        $region_name = 'Brazil (LGPD)';
    } elseif ($country === 'CA') {
        $region = 'pipeda';
        $region_name = 'Canada (PIPEDA)';
    } else {
        $region = 'default';
        $region_name = 'Rest of World';
    }
    
    // Get config
    $requires_consent = in_array($region, array('eu_uk', 'lgpd', 'pipeda'));
    $show_reject = in_array($region, array('eu_uk', 'lgpd'));
    $enable_ccpa = ($region === 'ccpa');
    
    wp_send_json_success(array(
        'country' => $country,
        'region' => $region,
        'region_name' => $region_name,
        'requires_consent' => $requires_consent,
        'show_reject' => $show_reject,
        'enable_ccpa' => $enable_ccpa,
    ));
}

/**
 * Clear geolocation cache
 */
function mbr_cc_ajax_clear_geo_cache() {
    check_ajax_referer('mbr_cc_geo_cache', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    global $wpdb;
    
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_mbr_cc_geo_%'");
    $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_mbr_cc_geo_%'");
    
    wp_send_json_success('Cache cleared');
}

// Register AJAX handlers
add_action('wp_ajax_mbr_cc_test_geolocation', 'mbr_cc_ajax_test_geolocation');
add_action('wp_ajax_mbr_cc_clear_geo_cache', 'mbr_cc_ajax_clear_geo_cache');
