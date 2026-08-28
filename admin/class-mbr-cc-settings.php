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
        add_action('wp_ajax_mbr_cc_preview_banner', array($this, 'ajax_preview_banner'));
    }
    
    /**
     * Register plugin settings.
     */
    public function register_settings() {
        $bool   = array('sanitize_callback' => 'rest_sanitize_boolean');
        $text   = array('sanitize_callback' => 'sanitize_text_field');
        $area   = array('sanitize_callback' => 'sanitize_textarea_field');
        $url    = array('sanitize_callback' => 'esc_url_raw');
        $color  = array('sanitize_callback' => 'sanitize_hex_color');
        $int    = array('sanitize_callback' => 'absint');
        $css    = array('sanitize_callback' => array($this, 'sanitize_css'));

        // Banner settings.
        register_setting('mbr_cc_settings', 'mbr_cc_banner_position', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_banner_layout', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_primary_color', $color);
        register_setting('mbr_cc_settings', 'mbr_cc_accept_button_color', $color);
        register_setting('mbr_cc_settings', 'mbr_cc_reject_button_color', $color);
        register_setting('mbr_cc_settings', 'mbr_cc_text_color', $color);
        register_setting('mbr_cc_settings', 'mbr_cc_revisit_button_text_color', $color);
        register_setting('mbr_cc_settings', 'mbr_cc_show_reject_button', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_show_customize_button', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_show_close_button', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_reload_on_consent', $bool);
        
        // Text settings.
        register_setting('mbr_cc_settings', 'mbr_cc_banner_heading', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_banner_description', $area);
        register_setting('mbr_cc_settings', 'mbr_cc_accept_button_text', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_reject_button_text', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_customize_button_text', $text);
        
        // Cookie settings.
        register_setting('mbr_cc_settings', 'mbr_cc_cookie_expiry_days', $int);
        
        // CCPA settings.
        register_setting('mbr_cc_settings', 'mbr_cc_enable_ccpa', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_ccpa_link_text', $text);
        
        // Revisit consent.
        register_setting('mbr_cc_settings', 'mbr_cc_revisit_consent_enabled', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_revisit_consent_text', $text);
        
        // Policy links.
        register_setting('mbr_cc_settings', 'mbr_cc_show_privacy_policy_link', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_privacy_policy_url', $url);
        register_setting('mbr_cc_settings', 'mbr_cc_privacy_policy_text', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_show_cookie_policy_link', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_cookie_policy_url', $url);
        register_setting('mbr_cc_settings', 'mbr_cc_cookie_policy_text', $text);
        
        // Branding.
        register_setting('mbr_cc_settings', 'mbr_cc_banner_logo_url', $url);
        register_setting('mbr_cc_settings', 'mbr_cc_banner_logo_id', $int);
        
        // Google Consent Mode v2.
        register_setting('mbr_cc_settings', 'mbr_cc_google_consent_mode', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_google_default_deny', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_google_ads_redaction', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_google_url_passthrough', $bool);
        
        // Microsoft UET Consent Mode.
        register_setting('mbr_cc_settings', 'mbr_cc_microsoft_consent_mode', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_microsoft_default_deny', $bool);
        
        // Internationalization & Accessibility.
        register_setting('mbr_cc_settings', 'mbr_cc_auto_translate', $bool);
        // A checkbox, and it must be sanitised as one. Registered as text until
        // 2.3.5, which stored the literal strings "true" and "false" — see the
        // note on wcag_compliance in MBR_CC_Import_Export::scalar_map().
        register_setting('mbr_cc_settings', 'mbr_cc_wcag_compliance', $bool);
        
        // Page-Specific Controls.
        register_setting('mbr_cc_settings', 'mbr_cc_excluded_pages', $area);
        register_setting('mbr_cc_settings', 'mbr_cc_excluded_url_patterns', $area);
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_login', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_checkout', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_cart', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_exclude_account', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_exclusions_skip_blocking', $bool);
        
        // Custom CSS.
        register_setting('mbr_cc_settings', 'mbr_cc_custom_css', $css);
        
        // Subdomain Consent.
        register_setting('mbr_cc_settings', 'mbr_cc_subdomain_sharing', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_subdomain_root_domain', $text);
        
        register_setting('mbr_cc_settings', 'mbr_cc_publisher_country_code', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_purpose_one_treatment', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_gdpr_applies', $bool);
        
        
        // AI / LLM training disclosure (Connecticut SB 1295, effective 1 July 2026).
        register_setting('mbr_cc_settings', 'mbr_cc_ai_training_enabled', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_ai_training_own', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_ai_training_vendors', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_ai_training_sell', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_ai_training_detail', $text);
        
        // Blocked Content Overlay (v1.7.0).
        register_setting('mbr_cc_settings', 'mbr_cc_blocked_overlay_enabled', $bool);
        register_setting('mbr_cc_settings', 'mbr_cc_blocked_overlay_heading', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_blocked_overlay_message', $area);
        register_setting('mbr_cc_settings', 'mbr_cc_blocked_overlay_btn_text', $text);
        register_setting('mbr_cc_settings', 'mbr_cc_blocked_overlay_logo_url', $url);
        register_setting('mbr_cc_settings', 'mbr_cc_blocked_overlay_logo_id', $int);
    }

    /**
     * Sanitize the custom CSS option.
     *
     * Strips any HTML tags while preserving CSS rules and line breaks.
     *
     * @param string $css Raw CSS input.
     * @return string Sanitized CSS.
     */
    public function sanitize_css($css) {
        return wp_strip_all_tags((string) $css);
    }
    
    /**
     * AJAX: Save settings.
     */
    public function ajax_save_settings() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        // WordPress slashes the whole of $_POST in wp_magic_quotes(), and none
        // of the sanitisers below strip backslashes. Without wp_unslash() here
        // the escaping is stored verbatim, rendered back into the field by
        // esc_textarea(), and re-slashed on the next save — so every round trip
        // doubled the backslashes in front of a quote.
        $settings = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        
        if (empty($settings)) {
            wp_send_json_error(array('message' => 'No settings provided.'));
        }
        
        // Only keys the plugin recognises may be written. Previously any key
        // was accepted and concatenated straight onto the mbr_cc_ prefix, so a
        // typo — or a compromised admin session — could create arbitrary
        // option rows. The import/export class already maintains the canonical
        // list of settings and their types, so it is reused here rather than
        // duplicated.
        $importer = class_exists('MBR_CC_Import_Export') ? MBR_CC_Import_Export::get_instance() : null;
        
        $applied = 0;
        $skipped = array();
        
        foreach ($settings as $key => $value) {
            $short = sanitize_key($key);
            
            if ($short === '') {
                continue;
            }
            
            $type = $importer ? $importer->resolve_sanitiser($short) : null;
            
            if ($type === null) {
                $skipped[] = $short;
                continue;
            }
            
            $option_key = 'mbr_cc_' . $short;
            
            if ($importer) {
                $value = $importer->sanitize_value($value, $type);
            } elseif (is_bool($value) || $value === 'true' || $value === 'false') {
                $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif (is_numeric($value)) {
                $value = intval($value);
            } else {
                $value = sanitize_text_field($value);
            }
            
            update_option($option_key, $value);
            $applied++;
        }
        
        if ($applied === 0 && !empty($skipped)) {
            wp_send_json_error(array(
                'message' => __('No recognised settings were supplied.', 'mbr-cookie-consent'),
                'skipped' => $skipped,
            ));
        }
        
        // Push the new values back into WPML/Polylang. Without this their
        // registered copies keep the wording from whenever the strings were
        // first seen, and edits made here never reach a multilingual front end.
        if (class_exists('MBR_CC_I18n_Accessibility')) {
            MBR_CC_I18n_Accessibility::get_instance()->register_multilingual_strings();
        }
        
        // The banner is rendered into the page, so a cached front end keeps
        // serving the old wording until something purges it.
        MBR_CC_Cache::flush('settings');
        
        wp_send_json_success(array(
            'message' => 'Settings saved successfully.',
            'applied' => $applied,
            'skipped' => $skipped,
        ));
    }
    
    /**
     * AJAX: Add blocked script.
     */
    public function ajax_add_blocked_script() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        // Unslash before sanitising, never after: the sanitisers leave
        // backslashes alone, so anything not unslashed here is stored escaped
        // and re-escaped on every subsequent save.
        $script = array(
            'name' => isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '',
            'identifier' => isset($_POST['identifier']) ? sanitize_text_field(wp_unslash($_POST['identifier'])) : '',
            'type' => isset($_POST['type']) ? sanitize_text_field(wp_unslash($_POST['type'])) : 'src',
            'category' => isset($_POST['category']) ? sanitize_text_field(wp_unslash($_POST['category'])) : 'marketing',
            'description' => isset($_POST['description']) ? sanitize_textarea_field(wp_unslash($_POST['description'])) : '',
        );
        
        if (empty($script['name']) || empty($script['identifier'])) {
            wp_send_json_error(array('message' => 'Name and identifier are required.'));
        }
        
        $blocker = MBR_CC_Script_Blocker::get_instance();
        $result = $blocker->add_blocked_script($script);
        
        if ($result) {
            MBR_CC_Cache::flush('blocked_scripts');
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
            MBR_CC_Cache::flush('blocked_scripts');
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
        
        $categories = isset($_POST['categories']) ? wp_unslash($_POST['categories']) : array();
        
        if (empty($categories)) {
            wp_send_json_error(array('message' => 'No categories provided.'));
        }
        
        // Sanitize categories.
        $sanitized = array();
        foreach ($categories as $slug => $category) {
            // A category arriving as a string rather than an array is a
            // TypeError on PHP 8. The import path already guards this; the AJAX
            // path was never updated to match.
            if (!is_array($category)) {
                continue;
            }
            
            $sanitized[sanitize_key($slug)] = array(
                'name' => sanitize_text_field($category['name'] ?? ''),
                'description' => sanitize_textarea_field($category['description'] ?? ''),
                'required' => isset($category['required']) && filter_var($category['required'], FILTER_VALIDATE_BOOLEAN),
                'enabled' => isset($category['enabled']) && filter_var($category['enabled'], FILTER_VALIDATE_BOOLEAN),
            );
        }
        
        $manager = MBR_CC_Consent_Manager::get_instance();
        $result = $manager->update_categories($sanitized);
        
        if ($result) {
            MBR_CC_Cache::flush('categories');
            wp_send_json_success(array('message' => 'Categories updated successfully.'));
        } else {
            wp_send_json_error(array('message' => 'Failed to update categories.'));
        }
    }

    /**
     * AJAX: render a live preview of the banner.
     *
     * The preview reflects the settings currently on screen, including unsaved
     * changes, so it renders from posted values rather than stored ones. Rather
     * than reimplementing the banner markup here — which would drift from the
     * real thing the moment either side changed — the posted values are pushed
     * in front of get_option() using `pre_option_` filters and the genuine
     * front-end renderer is called. What you see is what the front end builds.
     *
     * Output is returned as a complete HTML document for an iframe. The banner
     * stylesheet is full of position:fixed and !important rules; injected
     * directly into wp-admin it would wreck the page around it.
     */
    public function ajax_preview_banner() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized'));
        }
        
        $posted = isset($_POST['settings']) ? wp_unslash($_POST['settings']) : array();
        
        if (!is_array($posted)) {
            $posted = array();
        }
        
        $importer = class_exists('MBR_CC_Import_Export') ? MBR_CC_Import_Export::get_instance() : null;
        $overrides = array();
        
        foreach ($posted as $key => $value) {
            $short = sanitize_key($key);
            
            if ($short === '' || !$importer) {
                continue;
            }
            
            $type = $importer->resolve_sanitiser($short);
            
            if ($type === null) {
                continue;
            }
            
            $value = $importer->sanitize_value($value, $type);
            
            // WordPress treats a `pre_option_` filter returning false as "no
            // override", so a real boolean false would silently fall through to
            // the saved value — un-ticking a box and previewing would show the
            // box still ticked. Booleans are therefore converted to the form
            // the options table actually stores ('' and '1'), which also makes
            // the preview match exactly what a save would produce.
            if (is_bool($value)) {
                $value = $value ? '1' : '';
            }
            
            $overrides['mbr_cc_' . $short] = $value;
        }
        
        // Push the posted values in front of get_option() for this request only.
        $filters = array();
        
        foreach ($overrides as $option_name => $option_value) {
            $callback = function () use ($option_value) {
                return $option_value;
            };
            
            $filters[$option_name] = $callback;
            add_filter('pre_option_' . $option_name, $callback);
        }
        
        // The preview is a static mock-up: consent must not be recorded, and
        // nothing may swap buttons out from under the admin who is trying to
        // look at their own configuration.
        //
        // Since 2.3.4 regional overrides are applied in the browser and no
        // longer reach this filter, so the preview shows this site's settings
        // by construction. The call stays as a guard against a third-party
        // callback doing something visitor-dependent here — which the filter
        // docblock in class-mbr-cc-banner.php asks callers not to do, but a
        // preview is the wrong place to find out someone ignored it.
        remove_all_filters('mbr_cc_banner_config');
        
        $banner = MBR_CC_Banner::get_instance();
        
        ob_start();
        $banner->render_banner(true);
        $markup = ob_get_clean();
        
        $css = MBR_CC_Banner::build_custom_css();
        
        // Release the overrides before anything else runs in this request.
        foreach ($filters as $option_name => $callback) {
            remove_filter('pre_option_' . $option_name, $callback);
        }
        
        $stylesheet = MBR_CC_PLUGIN_URL . 'assets/css/banner.css';
        // Must match the front end's cache-busting, or the preview renders
        // new markup against a stale stylesheet and looks broken.
        $version = function_exists('mbr_cc_asset_version')
            ? mbr_cc_asset_version('assets/css/banner.css')
            : (defined('MBR_CC_VERSION') ? MBR_CC_VERSION : '1.0.0');
        
        wp_send_json_success(array(
            'document' => $this->build_preview_document($markup, $css, $stylesheet, $version),
        ));
    }
    
    /**
     * Assemble the self-contained preview document.
     *
     * @param string $markup     Rendered banner markup.
     * @param string $css        Generated banner CSS.
     * @param string $stylesheet URL of the banner stylesheet.
     * @param string $version    Asset version.
     * @return string Full HTML document.
     */
    private function build_preview_document($markup, $css, $stylesheet, $version) {
        $mock = '<div class="mbr-cc-preview-page">'
              . '<div class="mbr-cc-preview-bar"></div>'
              . '<div class="mbr-cc-preview-hero"></div>'
              . '<div class="mbr-cc-preview-lines">'
              . str_repeat('<span></span>', 7)
              . '</div></div>';
        
        // A neutral page behind the banner, so translucency and dark mode can
        // actually be judged. Deliberately plain — it is scaffolding, not a
        // suggestion of what the site looks like.
        $page_css = '
            html, body { margin: 0; padding: 0; height: 100%; }
            body { background: #f2f3f5; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
            .mbr-cc-preview-page { padding: 0 0 220px; }
            .mbr-cc-preview-bar { height: 54px; background: #ffffff; border-bottom: 1px solid #e3e5e9; }
            .mbr-cc-preview-hero {
                height: 190px;
                background: linear-gradient(135deg, #c9d4e3 0%, #aab9cc 45%, #8fa3bb 100%);
            }
            .mbr-cc-preview-lines { padding: 28px 32px; }
            .mbr-cc-preview-lines span {
                display: block; height: 12px; border-radius: 6px;
                background: #dcdfe4; margin-bottom: 14px;
            }
            .mbr-cc-preview-lines span:nth-child(3n) { width: 72%; }
            .mbr-cc-preview-lines span:nth-child(4n) { width: 88%; }
            /* Inert: this is a picture of a banner, not a working one. */
            .mbr-cc-banner button, .mbr-cc-banner a,
            .mbr-cc-modal button, .mbr-cc-modal a { cursor: default !important; }
        ';
        
        $doc  = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $doc .= '<meta name="viewport" content="width=device-width, initial-scale=1">';
        $doc .= '<link rel="stylesheet" href="' . esc_url($stylesheet . '?ver=' . $version) . '">';
        $doc .= '<style>' . $page_css . '</style>';
        $doc .= '<style>' . $css . '</style>';
        $doc .= '</head><body>' . $mock . $markup;
        // The banner ships hidden and is revealed by banner.js, which is not
        // loaded here. Reveal it directly instead of pulling in consent logic.
        $doc .= '<script>(function(){'
              . 'var b=document.querySelector(".mbr-cc-banner");'
              . 'if(b){b.style.display="block";b.style.opacity="1";}'
              . 'var o=document.getElementById("mbr-cc-popup-overlay");'
              . 'if(o){o.style.display="block";}'
              . 'document.addEventListener("click",function(e){e.preventDefault();},true);'
              . '})();</script>';
        $doc .= '</body></html>';
        
        return $doc;
    }
}
