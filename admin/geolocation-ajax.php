<?php
/**
 * Geolocation AJAX Handlers
 *
 * @package MBR_Cookie_Consent
 * @version 2.3.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Test geolocation for a specific country / region.
 *
 * Accepts an optional 'region' POST parameter (ISO 3166-2 sub-national code,
 * e.g. "QC" for Quebec) so admins can verify Quebec-specific behaviour.
 */
function mbr_cc_ajax_test_geolocation() {
    check_ajax_referer('mbr_cc_geo_test', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Permission denied');
    }
    
    $country = isset($_POST['country']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['country']))) : '';
    $region_code = isset($_POST['region']) ? strtoupper(sanitize_text_field(wp_unslash($_POST['region']))) : '';
    
    if (strlen($country) !== 2) {
        wp_send_json_error('Invalid country code');
    }
    
    // Resolve region using the same logic as live detection so the test
    // always matches what real visitors would experience.
    if (!function_exists('mbr_cc_geolocation') || !function_exists('mbr_cc_region_config')) {
        wp_send_json_error('Geolocation services not available');
    }
    
    $geo = mbr_cc_geolocation();
    
    // Use reflection to invoke the private determine_region() method without
    // duplicating the country/region mapping table here.
    try {
        $ref = new ReflectionMethod($geo, 'determine_region');
        $ref->setAccessible(true);
        $region = $ref->invoke($geo, $country, $region_code ?: null);
    } catch (Exception $e) {
        wp_send_json_error('Region resolution failed: ' . $e->getMessage());
    }
    
    // Pull the friendly name and behaviour flags from the canonical sources.
    //
    // These were previously hardcoded here as three parallel arrays, which meant
    // every region added after 2.1.0 (Vietnam, Indonesia, and then the 2.3.0
    // batch) reported as "Rest of World" in this tool even though live detection
    // was resolving them correctly. Reading the real configuration instead means
    // this tool cannot drift from actual banner behaviour again.
    // get_client_config() is the same method the REST route hands to the
    // browser, so this tool reports what the visitor gets and cannot report
    // anything else.
    //
    // It used to read keys straight off the region array, which is how it came
    // to announce "Requires Consent: Yes" and "DUAA Exempt Categories:
    // Analytics, Preferences" for regions where the banner implemented neither.
    // Those keys existed, so the tool printed them; nothing on the front end
    // had ever read one. An administrator testing GB got a confident report of
    // behaviour that did not exist, which is worse than no test tool, because a
    // wrong answer gets believed.
    $region_name = $geo->get_region_name($region);
    $cfg         = mbr_cc_region_config()->get_client_config($region);
    
    wp_send_json_success(array(
        'country'        => $country,
        'region_input'   => $region_code,
        'region'         => $region,
        'region_name'    => $region_name,
        'show_reject'    => !empty($cfg['show_reject_button']),
        'show_customize' => !empty($cfg['show_customize_button']),
        'enable_ccpa'    => !empty($cfg['enable_ccpa']),
        'banner_heading' => isset($cfg['banner_heading']) ? $cfg['banner_heading'] : '',
        'geo_enabled'    => MBR_CC_Region_Config::is_enabled(),
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
