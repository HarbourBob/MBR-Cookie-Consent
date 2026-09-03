<?php
/**
 * Geolocation Settings View
 *
 * @package MBR_Cookie_Consent
 * @version 2.1.0
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

// How that country was arrived at. A fallback must never be presented as a
// detection: a site whose lookups all fail sits in the wrong privacy regime
// indefinitely, and the only clue is a country that happens to look plausible.
$detection_source = method_exists($geo, 'get_detection_source') ? $geo->get_detection_source() : 'provider';
$is_fallback      = ($detection_source === 'default');

// Is the request reaching us through Cloudflare, and is the country header on?
$behind_cloudflare = function_exists('mbr_cc_request_is_cloudflare') && mbr_cc_request_is_cloudflare();
$cf_country_header = !empty($_SERVER['HTTP_CF_IPCOUNTRY']);

$provider_setting = get_option('mbr_cc_geolocation_provider', MBR_CC_Geolocation::DEFAULT_PROVIDER);
$ipapi_key_set    = trim((string) get_option('mbr_cc_ipapi_key', '')) !== '';
$insecure_opt_in  = (bool) get_option('mbr_cc_allow_insecure_geo_lookup', false);
?>

<div class="mbr-cc-settings-section">
    <h2><?php esc_html_e('Geolocation & Regional Compliance', 'mbr-cookie-consent'); ?></h2>
    <p><?php esc_html_e('Automatically detect user location and apply appropriate privacy law requirements (EU/EEA GDPR, UK DUAA, US Multi-State/GPC, Quebec Law 25, PIPEDA, Switzerland nFADP, Australia Privacy Act, Brazil LGPD, India DPDP, etc.)', 'mbr-cookie-consent'); ?></p>
    
    <div class="mbr-cc-info-box" style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 12px 16px; margin: 16px 0;">
        <p style="margin: 0;">
            <strong><?php esc_html_e('Your own banner text always wins.', 'mbr-cookie-consent'); ?></strong>
            <?php esc_html_e('Each region ships suggested wording — the UK text mentions the PECR exemptions, the US text mentions opt-out rights, and so on. That wording is only used where you have left the banner heading and description at their defaults. If you have written your own text on the Settings screen, visitors see your text everywhere, in every region.', 'mbr-cookie-consent'); ?>
        </p>
        <p style="margin: 8px 0 0 0;">
            <?php esc_html_e('Buttons are different: which of Accept, Reject and the "Do Not Sell or Share" link appear is decided by the region, because that part is a legal requirement rather than a matter of wording.', 'mbr-cookie-consent'); ?>
        </p>
    </div>
    
    <!-- Current Detection Status -->
    <div class="mbr-cc-info-box" style="background: <?php echo $is_fallback ? '#fcf3e7' : '#e7f3e7'; ?>; border-color: <?php echo $is_fallback ? '#dba617' : '#46b450'; ?>;">
        <h3 style="margin-top: 0;"><?php esc_html_e('Current Detection', 'mbr-cookie-consent'); ?></h3>
        <p><strong><?php esc_html_e('Your Country:', 'mbr-cookie-consent'); ?></strong> <?php echo esc_html($current_country ? $current_country : 'Not Detected'); ?></p>
        <p><strong><?php esc_html_e('Privacy Region:', 'mbr-cookie-consent'); ?></strong> <?php echo esc_html($region_name); ?></p>
        <p style="margin-bottom: 0;">
            <strong><?php esc_html_e('Source:', 'mbr-cookie-consent'); ?></strong>
            <?php
            switch ($detection_source) {
                case 'cloudflare':
                    esc_html_e('Cloudflare CF-IPCountry header — no outbound lookup, no visitor IP sent to a third party.', 'mbr-cookie-consent');
                    break;
                case 'ipapi_fallback':
                    esc_html_e('ipapi.co. You have selected ip-api.com, but it is not usable as configured — see below.', 'mbr-cookie-consent');
                    break;
                case 'provider':
                    esc_html_e('Your configured IP lookup provider.', 'mbr-cookie-consent');
                    break;
                default:
                    esc_html_e('NOT DETECTED. This is your configured default region, not a lookup result.', 'mbr-cookie-consent');
            }
            ?>
        </p>
        <?php if ($is_fallback) : ?>
            <p style="margin: 10px 0 0 0;">
                <?php esc_html_e('Nothing above was detected. Every visitor whose location cannot be resolved is being shown this region, so if it is not the region you expect, your visitors are seeing the wrong banner. Common causes: outbound HTTP blocked by your host, the lookup provider rate-limiting you, or a provider that cannot answer as configured.', 'mbr-cookie-consent'); ?>
            </p>
        <?php endif; ?>
        <p style="font-size: 12px; color: #666; margin: 10px 0 0 0;">
            <?php esc_html_e('This is what visitors from your current IP address would see.', 'mbr-cookie-consent'); ?>
        </p>
    </div>

    <?php if ($provider_setting === 'ip-api' && !$ipapi_key_set && !$insecure_opt_in) : ?>
        <div class="notice notice-warning inline" style="margin: 16px 0; padding: 10px 14px;">
            <p style="margin: 0 0 6px 0;">
                <strong><?php esc_html_e('ip-api.com cannot be used as configured.', 'mbr-cookie-consent'); ?></strong>
                <?php esc_html_e('Its free endpoint is plain HTTP only, and this plugin will not send a visitor IP address over an unencrypted connection without an explicit opt-in — over plain HTTP anyone on the path can rewrite the country returned and silently change which privacy law your visitors get.', 'mbr-cookie-consent'); ?>
            </p>
            <p style="margin: 0;">
                <?php esc_html_e('Lookups are being served by ipapi.co instead. To use ip-api.com, either add a pro key below or switch to ipapi.co to remove this notice.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    <?php endif; ?>

    <?php if ($behind_cloudflare && !$cf_country_header) : ?>
        <div class="notice notice-info inline" style="margin: 16px 0; padding: 10px 14px;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('This request came through Cloudflare, but the country header is not switched on.', 'mbr-cookie-consent'); ?></strong>
                <?php esc_html_e('Cloudflare can tell this plugin the visitor\'s country for free, with no outbound request and no visitor IP leaving your server — but CF-IPCountry is not sent by default. Switch it on in your Cloudflare dashboard, per domain, either way round:', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="margin: 8px 0 0 20px; list-style: disc;">
                <li><?php esc_html_e('Network › IP Geolocation › On. This adds the country header only, which is all this plugin uses. It is the quicker option.', 'mbr-cookie-consent'); ?></li>
                <li><?php esc_html_e('Or Rules › Settings › Managed Transforms tab › enable "Add visitor location headers", which adds city, region, continent and coordinates as well. On older dashboards this sits under Rules › Transform Rules › Managed Transforms.', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 8px 0 0 0;">
                <?php esc_html_e('Either setting applies to one zone — a single domain or subdomain added to Cloudflare — so it has to be enabled per site. Geolocation will then use the header automatically, whichever provider is selected below.', 'mbr-cookie-consent'); ?>
            </p>
            <p style="margin: 8px 0 0 0; font-size: 12px; color: #555;">
                <?php esc_html_e('If the header still does not arrive, check that the "Remove visitor IP headers" Managed Transform is not enabled — it strips visitor IP headers on the way to your server.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    <?php elseif ($behind_cloudflare && $cf_country_header) : ?>
        <div class="notice notice-success inline" style="margin: 16px 0; padding: 10px 14px;">
            <p style="margin: 0;">
                <strong><?php esc_html_e('Cloudflare detected.', 'mbr-cookie-consent'); ?></strong>
                <?php esc_html_e('The CF-IPCountry header is present and trusted, so it is used automatically for every visitor. No outbound lookup is made and no visitor IP address is sent to a third party. The provider setting below is only a fallback for requests that do not arrive through Cloudflare.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    <?php endif; ?>
    
    <!-- Enable Geolocation -->
    <div class="mbr-cc-form-row" style="margin-top: 25px;">
        <div class="mbr-cc-form-field">
            <label>
                <input type="checkbox" name="mbr_cc_geolocation_enabled" value="1" <?php checked(get_option('mbr_cc_geolocation_enabled', false)); ?>>
                <?php esc_html_e('Enable Automatic Geolocation', 'mbr-cookie-consent'); ?>
            </label>
            <p class="description"><?php esc_html_e('Automatically detect user location and apply region-specific privacy requirements.', 'mbr-cookie-consent'); ?></p>
        </div>
    </div>
    
    <!-- API Provider Selection -->
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label for="geolocation_provider"><?php esc_html_e('IP Lookup Provider', 'mbr-cookie-consent'); ?></label>
            <select name="mbr_cc_geolocation_provider" id="geolocation_provider">
                <option value="ipapi" <?php selected(get_option('mbr_cc_geolocation_provider', MBR_CC_Geolocation::DEFAULT_PROVIDER), 'ipapi'); ?>>
                    <?php esc_html_e('ipapi.co — HTTPS, free, 1000 req/day', 'mbr-cookie-consent'); ?>
                </option>
                <option value="cloudflare" <?php selected(get_option('mbr_cc_geolocation_provider', MBR_CC_Geolocation::DEFAULT_PROVIDER), 'cloudflare'); ?>>
                    <?php esc_html_e('Cloudflare headers — no outbound request', 'mbr-cookie-consent'); ?>
                </option>
                <option value="ip-api" <?php selected(get_option('mbr_cc_geolocation_provider', MBR_CC_Geolocation::DEFAULT_PROVIDER), 'ip-api'); ?>>
                    <?php esc_html_e('ip-api.com — 45 req/min (HTTPS requires a pro key)', 'mbr-cookie-consent'); ?>
                </option>
            </select>
            <p class="description">
                <?php esc_html_e('Cloudflare is fastest as it needs no outbound request. ip-api.com offers a higher free rate limit but only serves HTTPS on its paid tier — see the transport note below.', 'mbr-cookie-consent'); ?>
            </p>
            <p class="description" style="margin-top: 10px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;">
                <strong><?php esc_html_e('Check your provider\'s terms before going live.', 'mbr-cookie-consent'); ?></strong>
                <?php esc_html_e('Both lookup services are third parties with their own licensing. Their free tiers are generally offered for non-commercial, testing or development use, and those terms change without notice. If this is a commercial site, confirm you are within your chosen provider\'s current terms — or use the Cloudflare option, which resolves the country from a header your CDN already sends and makes no outbound request at all.', 'mbr-cookie-consent'); ?>
            </p>
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
    
    <!-- ip-api.com transport -->
    <div class="mbr-cc-form-row" id="mbr-cc-ipapi-transport">
        <div class="mbr-cc-form-field">
            <label for="ipapi_key"><?php esc_html_e('ip-api.com Pro Key (optional)', 'mbr-cookie-consent'); ?></label>
            <input type="text" name="mbr_cc_ipapi_key" id="ipapi_key"
                   value="<?php echo esc_attr(get_option('mbr_cc_ipapi_key', '')); ?>"
                   style="width: 100%;" autocomplete="off">
            <p class="description">
                <?php esc_html_e('With a key, lookups use the HTTPS endpoint. Without one, ip-api.com can only be queried over plain HTTP.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <div class="mbr-cc-form-field">
            <label>
                <input type="checkbox" name="mbr_cc_trust_cloudflare_headers" value="1"
                       <?php checked(get_option('mbr_cc_trust_cloudflare_headers', false)); ?>>
                <?php esc_html_e('My origin only accepts traffic from Cloudflare', 'mbr-cookie-consent'); ?>
            </label>
            <p class="description">
                <?php esc_html_e('Leave this off unless it is true. Cloudflare headers are normally trusted only when the request provably came through Cloudflare — either it arrived from a published Cloudflare address, or your host has already restored the visitor IP from Cloudflare\'s own header. Between them those cover almost every Cloudflare site, so you should not need this.', 'mbr-cookie-consent'); ?>
            </p>
            <p class="description">
                <?php esc_html_e('Tick it only if your origin is firewalled to Cloudflare\'s IP ranges or uses Authenticated Origin Pulls, so that no request can reach the site any other way. Neither is visible from PHP, which is why it has to be asserted. On a site that is reachable directly, this would let a visitor set their own country header and choose which privacy law they are shown.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    </div>

    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label>
                <input type="checkbox" name="mbr_cc_allow_insecure_geo_lookup" value="1"
                       <?php checked(get_option('mbr_cc_allow_insecure_geo_lookup', false)); ?>>
                <?php esc_html_e('Allow ip-api.com lookups over plain HTTP', 'mbr-cookie-consent'); ?>
            </label>
            <p class="description">
                <strong><?php esc_html_e('Not recommended.', 'mbr-cookie-consent'); ?></strong>
                <?php esc_html_e('Over plain HTTP, anyone able to observe the connection can rewrite the country returned and change which privacy regime your visitors are shown — and visitor IP addresses travel to a third party unencrypted. Leave this off unless you understand and accept that.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
    </div>
    
    <!-- Proxy trust -->
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label for="proxy_mode"><?php esc_html_e('Visitor IP Detection', 'mbr-cookie-consent'); ?></label>
            <?php $mbr_cc_proxy_mode = function_exists('mbr_cc_get_proxy_mode') ? mbr_cc_get_proxy_mode() : 'auto'; ?>
            <select name="mbr_cc_proxy_mode" id="proxy_mode">
                <option value="auto" <?php selected($mbr_cc_proxy_mode, 'auto'); ?>>
                    <?php esc_html_e('Automatic (recommended) — detects Cloudflare safely', 'mbr-cookie-consent'); ?>
                </option>
                <option value="proxy" <?php selected($mbr_cc_proxy_mode, 'proxy'); ?>>
                    <?php esc_html_e('Behind another reverse proxy or load balancer', 'mbr-cookie-consent'); ?>
                </option>
                <option value="none" <?php selected($mbr_cc_proxy_mode, 'none'); ?>>
                    <?php esc_html_e('Direct connection — never trust forwarding headers', 'mbr-cookie-consent'); ?>
                </option>
            </select>
            <p class="description">
                <?php esc_html_e('Forwarding headers such as X-Forwarded-For can be set by any visitor, so they are only honoured when the request demonstrably arrived through a proxy. On Automatic, Cloudflare\'s header is accepted only when the request genuinely came from a Cloudflare address, so no configuration is needed.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <div class="mbr-cc-form-field">
            <label for="trusted_proxies"><?php esc_html_e('Trusted Proxy Addresses', 'mbr-cookie-consent'); ?></label>
            <textarea name="mbr_cc_trusted_proxies" id="trusted_proxies" rows="3" style="width: 100%;"
                      placeholder="10.0.0.0/8&#10;192.168.0.0/16"><?php echo esc_textarea(get_option('mbr_cc_trusted_proxies', '')); ?></textarea>
            <p class="description">
                <?php esc_html_e('One IP address or CIDR range per line. Only used when the mode above is set to reverse proxy. Leave blank to trust the private and loopback ranges, which covers most setups.', 'mbr-cookie-consent'); ?>
            </p>
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
        
        <!-- EU/EEA GDPR -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #2271b1;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇪🇺 <?php esc_html_e('EU/EEA - GDPR / ePrivacy', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('EU-27 + Iceland, Liechtenstein, Norway — strict opt-in regime', 'mbr-cookie-consent'); ?>
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
        
        <!-- UK DUAA -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #0073aa;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇬🇧 <?php esc_html_e('UK - GDPR + DUAA 2025', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Separate regime since Feb 2026; ICO guidance finalised 29 Apr 2026', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('5 PECR exemptions (analytics, functionality, etc.)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('"Simple means of objecting" required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Advertising still requires consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Purpose limitation enforced', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Complaints procedure by June 2026', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to £17.5M or 4% of turnover (35x increase)', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- US Multi-State -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #d63638;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇺🇸 <?php esc_html_e('US - Multi-State + GPC', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('20 states (IN, KY, RI added Jan 2026); MD MODPA effective Oct 2025', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('"Do Not Sell or Share" link (CCPA)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Honour GPC signals (CA, CO, CT, DE, MD, MN, MT, NE, NH, NJ, OR, TX)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Opt-out based (not opt-in)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Visible "Opt-Out Honored" toast (CA, mandatory Jan 2026)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Sensitive data opt-in incl. neural data + under-16s (CA)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('No false-urgency dark patterns (CA)', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to $7,988 per intentional violation (CA); varies by state', 'mbr-cookie-consent'); ?>
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
        
        <!-- Canada PIPEDA -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #8c5e58;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇨🇦 <?php esc_html_e('Canada - PIPEDA / CASL', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Federal regime; Quebec visitors get the stricter Law 25 config', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Meaningful consent required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Purpose before collection', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Implied consent only in low-risk cases', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('CASL treats cookies as programs', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Bill C-27 died Jan 2025 — PIPEDA still governs', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to $10M CAD per violation', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- India DPDP -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #ff6b35;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇮🇳 <?php esc_html_e('India - DPDP Act + Rules 2025', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Rules notified 13 Nov 2025 — phased rollout to May 2027', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Granular consent + one-click withdrawal', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Standalone privacy notice', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Verifiable parental consent for minors', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('72-hour breach notification', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Full compliance by 13 May 2027', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to ₹250 crore (~£25M) per violation', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Vietnam PDPL -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #da251d;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇻🇳 <?php esc_html_e('Vietnam - PDPL (Law 91/2025)', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('In force 1 Jan 2026 — consent-centric, GDPR-like, extraterritorial', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Voluntary, specific, informed opt-in consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Silence is NOT consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Granular per-purpose consent (no bundling)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Easy withdrawal at any time', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Heightened protection for children', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to 5% of prior-year revenue; up to 10x unlawful data-trading gains', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Indonesia UU PDP -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #ce1126;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇮🇩 <?php esc_html_e('Indonesia - UU PDP (Law 27/2022)', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Fully effective 17 Oct 2024, upheld by Constitutional Court Jan 2026 — GDPR-style, extraterritorial', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Explicit, informed opt-in consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Purpose-specific consent required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Withdrawal must be honoured', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Cross-border transfers need safeguards', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Supervisory authority still developing — monitor', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Admin fines up to 2% of annual revenue; criminal penalties up to IDR 6 billion', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Quebec Law 25 -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #d62828;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇨🇦 <?php esc_html_e('Quebec - Law 25', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Stricter than PIPEDA — express opt-in, French-language banner', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Express opt-in (CAI rejects implied consent)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('French-language banner required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Detailed consent records kept', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Withdrawal at least as easy as consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Heightened protections for minors', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to CA$25M or 4% of worldwide turnover', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Switzerland nFADP -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #c8102e;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇨🇭 <?php esc_html_e('Switzerland - revFADP / nFADP', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Revised FADP in force since 1 September 2023 — GDPR-equivalent', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Consent for non-essential cookies', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Reject equally prominent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Transparent collection notice', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Free, informed, unambiguous consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('EU-recognised adequate protection', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Personal fines up to CHF 250,000 against responsible individuals', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Australia Privacy Act -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #00843d;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇦🇺 <?php esc_html_e('Australia - Privacy Act 1988', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Amended by Privacy and Other Legislation Amendment Act 2024', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('APP 5 notification at point of collection', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('APP 3 — collect only what is necessary', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Sensitive information requires opt-in', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('ADM transparency from 10 Dec 2026', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Children\'s Online Privacy Code by Dec 2026', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to AU$50M, 30% of adjusted turnover, or 3x benefit', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Nigeria NDPA + GAID -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #008751;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇳🇬 <?php esc_html_e('Nigeria - NDPA + GAID', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('GAID effective 19 Sep 2025 — prescribes banner placement explicitly', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Prominent homepage notice — footer link insufficient', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Genuine accept/decline choice required', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('No pre-ticked boxes, no implied consent', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Only strictly necessary cookies exempt', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Applies extraterritorially', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to ₦10M or 2% of annual gross revenue (controllers of major importance)', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- China PIPL -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #de2910;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇨🇳 <?php esc_html_e('China - PIPL', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('In force since 1 Nov 2021 — explicit consent, extraterritorial', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Explicit, voluntary, fully informed opt-in', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Identifying cookie IDs are personal information', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Separate consent for sensitive data', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Applies to foreign sites serving mainland users', 'mbr-cookie-consent'); ?></li>
                <li>⚠ <?php esc_html_e('Cross-border transfer consent NOT handled by this plugin', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #f8d7da; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Important:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Cross-border transfers also need a CAC assessment, standard contract or certification. Handle outside the banner.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- South Korea PIPA -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #003478;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇰🇷 <?php esc_html_e('South Korea - PIPA', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('One of Asia\'s strictest regimes with a highly active regulator', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Specific, informed, prior consent where cookies identify', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Notice-then-opt-out is not sufficient', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Must explain how to block or refuse cookies (PIPC, Apr 2025)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Behavioural advertising is a supervision priority', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Keep consent records — documentation expectations are high', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to 3% of relevant turnover, plus criminal liability', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Saudi Arabia PDPL -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #165d31;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇸🇦 <?php esc_html_e('Saudi Arabia - PDPL', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Grace period ended Sep 2024 — SDAIA enforcement now active', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Consent is the default lawful basis', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Opt-in for advertising and analytics cookies', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Arabic-language notices expected', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('48 violation decisions in first year of adjudication', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Marketing without prior consent a common finding', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Up to SAR 5M per breach, doubling for repeat violations', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- South Africa POPIA -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #007749;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🇿🇦 <?php esc_html_e('South Africa - POPIA', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('No cookie clause — the pressure point is direct marketing', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Section 69: opt-in for electronic direct marketing', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Narrow existing-customer exception only', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Guidance Note on Direct Marketing (Dec 2024) confirms strict reading', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Burden of proof for consent sits with you', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Functional/measurement cookies sit in a softer zone', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #fff3cd; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Penalties:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Administrative fines up to ZAR 10M, plus criminal penalties', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
        <!-- Rest of World -->
        <div style="border: 1px solid #ddd; padding: 20px; border-radius: 6px; border-left: 4px solid #999;">
            <h4 style="margin: 0 0 10px 0; display: flex; align-items: center; gap: 8px;">
                🌍 <?php esc_html_e('Rest of World', 'mbr-cookie-consent'); ?>
            </h4>
            <p style="font-size: 13px; color: #666; margin-bottom: 15px;">
                <?php esc_html_e('Countries without a dedicated region — safe opt-in default since 2.3.0', 'mbr-cookie-consent'); ?>
            </p>
            <ul style="font-size: 13px; line-height: 1.8; margin: 0;">
                <li>✓ <?php esc_html_e('Opt-in by default (new installs)', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('No implied consent from scrolling', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Reject option always available', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Provide privacy policy', 'mbr-cookie-consent'); ?></li>
                <li>✓ <?php esc_html_e('Allow preference management', 'mbr-cookie-consent'); ?></li>
            </ul>
            <p style="margin: 15px 0 0 0; padding: 10px; background: #f0f6fc; border-radius: 4px; font-size: 12px;">
                <strong><?php esc_html_e('Note:', 'mbr-cookie-consent'); ?></strong> 
                <?php esc_html_e('Deliberately over-compliant in notice-based markets such as Japan. Sites upgrading from an earlier version keep their previous settings.', 'mbr-cookie-consent'); ?>
            </p>
        </div>
        
    </div>
    
    <!-- Testing Tool -->
    <h3 style="margin-top: 40px;"><?php esc_html_e('Testing & Debugging', 'mbr-cookie-consent'); ?></h3>
    
    <div class="mbr-cc-form-row">
        <div class="mbr-cc-form-field">
            <label for="test_country"><?php esc_html_e('Test with Country Code', 'mbr-cookie-consent'); ?></label>
            <input type="text" id="test_country" placeholder="e.g., GB, FR, US, CA, NG, CN, KR" style="width: 200px;" maxlength="2">
            <input type="text" id="test_region" placeholder="<?php esc_attr_e('Region (optional, e.g. QC)', 'mbr-cookie-consent'); ?>" style="width: 220px;" maxlength="3">
            <button type="button" class="button" id="test-geolocation">
                <?php esc_html_e('Test Detection', 'mbr-cookie-consent'); ?>
            </button>
            <p class="description"><?php esc_html_e('Enter a 2-letter country code (and optional ISO 3166-2 region — e.g. CA + QC for Quebec) to see what banner configuration would be used for visitors from that location.', 'mbr-cookie-consent'); ?></p>
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
        var region  = $('#test_region').val().toUpperCase();
        if (country.length !== 2) {
            alert('Please enter a valid 2-letter country code');
            return;
        }
        
        $('#test-results').html('<p>Testing...</p>').show();
        
        $.post(ajaxurl, {
            action: 'mbr_cc_test_geolocation',
            country: country,
            region:  region,
            nonce: '<?php echo esc_js(wp_create_nonce("mbr_cc_geo_test")); ?>'
        }, function(response) {
            if (response.success) {
                var html = '<div style="padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 4px;">';
                var heading = country + (region ? ' / ' + region : '');
                html += '<h4 style="margin: 0 0 10px 0;">Results for ' + heading + ':</h4>';
                html += '<p><strong>Region:</strong> ' + response.data.region_name + '</p>';
                html += '<p><strong>Show Reject Button:</strong> ' + (response.data.show_reject ? 'Yes' : 'No') + '</p>';
                html += '<p><strong>Show Customize Button:</strong> ' + (response.data.show_customize ? 'Yes' : 'No') + '</p>';
                html += '<p><strong>Enable CCPA Link:</strong> ' + (response.data.enable_ccpa ? 'Yes' : 'No') + '</p>';
                if (response.data.banner_heading) {
                    html += '<p><strong>Banner Heading:</strong> ' + $('<div/>').text(response.data.banner_heading).html() + '</p>';
                }
                if (!response.data.geo_enabled) {
                    html += '<p style="margin-top:12px;padding:8px;background:#fcf3cd;border-left:3px solid #dba617;">'
                          + '<strong>Note:</strong> regional detection is currently switched off, so live visitors '
                          + 'see this site\'s own banner settings regardless of where they are. '
                          + 'This test shows what they <em>would</em> see if you enabled it.</p>';
                }
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
            nonce: '<?php echo esc_js(wp_create_nonce("mbr_cc_geo_cache")); ?>'
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
