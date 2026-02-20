<?php
/**
 * Geolocation Settings View
 *
 * @package MBR_Cookie_Consent
 * @version 1.6.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

// Check if geolocation is available
if (!function_exists('mbr_cc_geolocation')) {
    echo '<div class="notice notice-error"><p>Geolocation feature not loaded. Please refresh the page.</p></div>';
    return;
}

$geo = mbr_cc_geolocation();
$region_config = mbr_cc_region_config();

// Get current detection
$current_country = $geo->get_country();
$current_region = $geo->get_region();
$region_name = $geo->get_region_name();
?>

<div class="mbr-cc-settings-section">
    <h2><?php esc_html_e('Geolocation & Regional Compliance', 'mbr-cookie-consent'); ?></h2>
    <p><?php esc_html_e('Automatically detect user location and apply appropriate privacy law requirements (GDPR, CCPA, LGPD, etc.)', 'mbr-cookie-consent'); ?></p>
    
    <!-- Current Detection Status -->
    <div class="mbr-cc-info-box" style="background: #e7f3e7; border-color: #46b450;">
        <h3 style="margin-top: 0;"><?php esc_html_e('Current Detection', 'mbr-cookie-consent'); ?></h3>
        <p><strong><?php esc_html_e('Your Country:', 'mbr-cookie-consent'); ?></strong> <?php echo esc_html($current_country ? $current_country : 'Not Detected'); ?></p>
        <p><strong><?php esc_html_e('Privacy Region:', 'mbr-cookie-consent'); ?></strong> <?php echo esc_html($region_name); ?></p>
        <p style="font-size: 12px; color: #666; margin-bottom: 0;">
            <?php esc_html_e('This is what visitors from your current IP address would see.', 'mbr-cookie-consent'); ?>
        </p>
    </div>
    
    <!-- Enable Geolocation -->
    <div class="mbr-cc-form-row" style="margin-top: 25px;">
        <div class="mbr-cc-form-field">
            <div class="mbr-cc-toggle-wrapper">
                    <label class="mbr-cc-toggle-switch">
                        <input type="checkbox" name="mbr_cc_geolocation_enabled" value="1" <?php checked(get_option('mbr_cc_geolocation_enabled', false)); ?>>
                        <span class="mbr-cc-toggle-slider"></span>
                    </label>
                    <div class="mbr-cc-toggle-label">
                        <strong><?php esc_html_e('Enable Automatic Geolocation', 'mbr-cookie-consent'); ?></strong>
                    </div>
                </div>
            <p class="description"><?php esc_html_e('Automatically detect user location and apply region-specific privacy requirements.', 'mbr-cookie-consent'); ?></p>
        </div>
    </div>
    
    <!-- API Provider Selection -->
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label for="geolocation_provider"><?php esc_html_e('IP Lookup Provider', 'mbr-cookie-consent'); ?></label>
            <select name="mbr_cc_geolocation_provider" id="geolocation_provider">
                <option value="ip-api" <?php selected(get_option('mbr_cc_geolocation_provider', 'ip-api'), 'ip-api'); ?>>
                    ip-api.com (Free, 45 req/min)
                </option>
                <option value="ipapi" <?php selected(get_option('mbr_cc_geolocation_provider', 'ip-api'), 'ipapi'); ?>>
                    ipapi.co (Free, 1000 req/day)
                </option>
                <option value="cloudflare" <?php selected(get_option('mbr_cc_geolocation_provider', 'ip-api'), 'cloudflare'); ?>>
                    Cloudflare Headers (If using Cloudflare)
                </option>
            </select>
            <p class="description"><?php esc_html_e('Choose which service to use for IP geolocation. Cloudflare is fastest if you use Cloudflare CDN.', 'mbr-cookie-consent'); ?></p>
        </div>
        
        <div class="mbr-cc-form-field">
            <label for="geolocation_cache"><?php esc_html_e('Cache Duration', 'mbr-cookie-consent'); ?></label>
            <select name="mbr_cc_geolocation_cache" id="geolocation_cache">
                <option value="3600" <?php selected(get_option('mbr_cc_geolocation_cache', 86400), 3600); ?>>1 Hour</option>
                <option value="43200" <?php selected(get_option('mbr_cc_geolocation_cache', 86400), 43200); ?>>12 Hours</option>
                <option value="86400" <?php selected(get_option('mbr_cc_geolocation_cache', 86400), 86400); ?>>24 Hours (Recommended)</option>
                <option value="604800" <?php selected(get_option('mbr_cc_geolocation_cache', 86400), 604800); ?>>7 Days</option>
                <option value="2592000" <?php selected(get_option('mbr_cc_geolocation_cache', 86400), 2592000); ?>>30 Days</option>
            </select>
            <p class="description"><?php esc_html_e('How long to cache geolocation results per IP address.', 'mbr-cookie-consent'); ?></p>
        </div>
    </div>
    
    <!-- Default Region -->
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label for="geolocation_default"><?php esc_html_e('Default Country (Fallback)', 'mbr-cookie-consent'); ?></label>
            <input type="text" name="mbr_cc_geolocation_default" id="geolocation_default" 
                   value="<?php echo esc_attr(get_option('mbr_cc_geolocation_default', 'US')); ?>" 
                   maxlength="2" style="width: 100px; text-transform: uppercase;">
            <p class="description"><?php esc_html_e('2-letter country code used when geolocation fails (e.g., US, GB, DE). Default: US', 'mbr-cookie-consent'); ?></p>
        </div>
    </div>
    
    <!-- Regional Compliance Information -->
    <h3 style="margin-top: 40px;"><?php esc_html_e('Regional Compliance Requirements', 'mbr-cookie-consent'); ?></h3>
    <p><?php esc_html_e('Understanding what each region requires for cookie consent compliance:', 'mbr-cookie-consent'); ?></p>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-top: 20px;">
        
        <!-- EU/UK GDPR -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #2271b1;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇪🇺 <?php esc_html_e('EU/UK - GDPR', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('27 EU countries + United Kingdom', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Explicit opt-in required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Reject equally prominent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('No pre-ticked boxes', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Show cookie categories', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Easy consent withdrawal', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to €20M or 4% of annual turnover', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- California CCPA -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #d63638;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇺🇸 <?php esc_html_e('California - CCPA', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('California residents (US)', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('"Do Not Sell" link required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Opt-out based (not opt-in)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Honor opt-out requests', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Disclose data practices', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Allow data deletion requests', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to $7,500 per violation', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Brazil LGPD -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #00a32a;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇧🇷 <?php esc_html_e('Brazil - LGPD', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Brazil (similar to GDPR)', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Clear consent required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Show legitimate purpose', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Users can revoke consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Data minimization', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Similar to GDPR rules', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to 2% of revenue (max R$50M)', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Rest of World -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #999;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🌍 <?php esc_html_e('Rest of World', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Other countries & regions', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Best practice transparency', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Provide privacy policy', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Allow preference management', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Respect user choices', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('May use implied consent', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #f0f6fc; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Note:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Requirements vary by jurisdiction', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
    </div>
    
    <!-- Testing Tool -->
    <h3 style="margin-top: 40px;"><?php esc_html_e('Testing & Debugging', 'mbr-cookie-consent'); ?></h3>
    
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label for="test_country"><?php esc_html_e('Test with Country Code', 'mbr-cookie-consent'); ?></label>
            <input type="text" id="test_country" placeholder="e.g., GB, FR, US, BR" style="width: 200px;" maxlength="2">
            <button type="button" class="button" id="test-geolocation">
                <?php esc_html_e('Test Detection', 'mbr-cookie-consent'); ?>
            </button>
            <p class="description"><?php esc_html_e('Enter a 2-letter country code to see what banner configuration would be used for visitors from that country.', 'mbr-cookie-consent'); ?></p>
            <div id="test-results" style="margin-top: 15px; display: none;"></div>
        </div>
    </div>
    
    <!-- Clear Cache -->
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <button type="button" class="button" id="clear-geo-cache">
                <?php esc_html_e('Clear Geolocation Cache', 'mbr-cookie-consent'); ?>
            </button>
            <p class="description"><?php esc_html_e('Clear cached geolocation data to force fresh lookups for all visitors.', 'mbr-cookie-consent'); ?></p>
        </div>
    </div>
    
    <!-- Legal Disclaimer -->
    <div style="margin-top: 30px; padding: 20px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px;">
        <h4 style="margin-top: 0;"><?php esc_html_e('⚖️ Legal Disclaimer', 'mbr-cookie-consent'); ?></h4>
        <p style="margin: 0; font-size: 13px; line-height: 1.8;">
            <?php esc_html_e('This plugin provides technical tools to help implement geolocation-based consent. It does NOT constitute legal advice. You are responsible for ensuring compliance with all applicable privacy laws. Consult with legal counsel regarding your specific requirements. Privacy laws change frequently - stay informed!', 'mbr-cookie-consent'); ?>
        </p>
    </div>
    
</div>

<script>
jQuery(document).ready(function($) {
    // Test geolocation
    $('#test-geolocation').on('click', function() {
        var country = $('#test_country').val().toUpperCase();
        if (country.length !== 2) {
            alert('Please enter a valid 2-letter country code');
            return;
        }
        
        $('#test-results').html('<p>Testing...</p>').show();
        
        $.post(ajaxurl, {
            action: 'mbr_cc_test_geolocation',
            country: country,
            nonce: '<?php echo wp_create_nonce("mbr_cc_geo_test"); ?>'
        }, function(response) {
            if (response.success) {
                var html = '<div style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
                html += '<h4 style="margin: 0 0 10px 0;">Results for ' + country + ':</h4>';
                html += '<p><strong>Region:</strong> ' + response.data.region_name + '</p>';
                html += '<p><strong>Requires Consent:</strong> ' + (response.data.requires_consent ? 'Yes' : 'No') + '</p>';
                html += '<p><strong>Show Reject Button:</strong> ' + (response.data.show_reject ? 'Yes' : 'No') + '</p>';
                html += '<p><strong>Enable CCPA Link:</strong> ' + (response.data.enable_ccpa ? 'Yes' : 'No') + '</p>';
                html += '</div>';
                $('#test-results').html(html);
            } else {
                $('#test-results').html('<p style="color: red;">Error: ' + response.data + '</p>');
            }
        });
    });
    
    // Clear cache
    $('#clear-geo-cache').on('click', function() {
        if (!confirm('Clear all geolocation cache data?')) {
            return;
        }
        
        $.post(ajaxurl, {
            action: 'mbr_cc_clear_geo_cache',
            nonce: '<?php echo wp_create_nonce("mbr_cc_geo_cache"); ?>'
        }, function(response) {
            if (response.success) {
                alert('Geolocation cache cleared successfully!');
            } else {
                alert('Error clearing cache: ' + response.data);
            }
        });
    });
});
</script>