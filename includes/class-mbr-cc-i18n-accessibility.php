<?php
/**
 * Internationalization and Accessibility Handler
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * I18n and Accessibility class.
 */
/**
 * Note on automatic translation.
 *
 * This class used to carry a server-side auto-translation path: it read the
 * visitor's Accept-Language header, looked the language up in a bundled PHP
 * table, and filtered the banner text. None of it ever ran — the filter it
 * hung on was never applied — and it could not have been made to run safely,
 * because choosing a language from a request header writes one visitor's
 * language into a page that a cache then serves to everybody.
 *
 * Automatic translation now lives in MBR_CC_Translations and happens in the
 * browser. This class remains responsible for WPML and Polylang integration,
 * which is safe to do server-side because those plugins vary the URL.
 */
class MBR_CC_I18n_Accessibility {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_I18n_Accessibility
     */
    private static $instance = null;
    
    /**
     * Supported languages with their native names.
     *
     * @var array
     */
    private static $supported_languages = array(
        'en' => 'English',
        'es' => 'Español',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'it' => 'Italiano',
        'pt' => 'Português',
        'nl' => 'Nederlands',
        'pl' => 'Polski',
        'ru' => 'Русский',
        'ja' => '日本語',
        'zh' => '中文',
        'ko' => '한국어',
        'ar' => 'العربية',
        'tr' => 'Türkçe',
        'sv' => 'Svenska',
        'da' => 'Dansk',
        'fi' => 'Suomi',
        'no' => 'Norsk',
        'cs' => 'Čeština',
        'hu' => 'Magyar',
        'ro' => 'Română',
        'el' => 'Ελληνικά',
        'bg' => 'Български',
        'uk' => 'Українська',
        'hr' => 'Hrvatski',
        'sk' => 'Slovenčina',
        'sl' => 'Slovenščina',
        'et' => 'Eesti',
        'lv' => 'Latviešu',
        'lt' => 'Lietuvių',
        'th' => 'ไทย',
        'vi' => 'Tiếng Việt',
        'id' => 'Bahasa Indonesia',
        'ms' => 'Bahasa Melayu',
        'he' => 'עברית',
        'hi' => 'हिन्दी',
        'bn' => 'বাংলা',
        'fa' => 'فارسی',
        'ca' => 'Català',
        'sr' => 'Српски',
        'af' => 'Afrikaans',
        'sq' => 'Shqip',
        'is' => 'Íslenska',
        'ga' => 'Gaeilge',
    );
    
    /**
     * Get instance.
     *
     * @return MBR_CC_I18n_Accessibility
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
        // Auto-translation hooks.
        
        // WPML/Polylang compatibility.
        add_action('init', array($this, 'register_multilingual_strings'));
        
        // Accessibility enhancements.
        add_action('wp_footer', array($this, 'add_accessibility_announcements'), 5);
    }
    
    /**
     * Register strings with WPML/Polylang.
     */
    public function register_multilingual_strings() {
        // Only register if WPML or Polylang is active.
        if (!function_exists('icl_register_string') && !function_exists('pll_register_string')) {
            return;
        }
        
        $strings = array(
            'banner_heading' => get_option('mbr_cc_banner_heading', 'We value your privacy'),
            'banner_description' => get_option('mbr_cc_banner_description', ''),
            'accept_button' => get_option('mbr_cc_accept_button_text', 'Accept All'),
            'reject_button' => get_option('mbr_cc_reject_button_text', 'Reject All'),
            'customize_button' => get_option('mbr_cc_customize_button_text', 'Customize'),
            'revisit_button' => get_option('mbr_cc_revisit_consent_text', 'Cookie Settings'),
            'ccpa_link' => get_option('mbr_cc_ccpa_link_text', 'Do Not Sell or Share My Personal Information'),
            'privacy_policy_text' => get_option('mbr_cc_privacy_policy_text', 'Privacy Policy'),
            'cookie_policy_text' => get_option('mbr_cc_cookie_policy_text', 'Cookie Policy'),
        );
        
        // Register with WPML.
        if (function_exists('icl_register_string')) {
            foreach ($strings as $name => $value) {
                icl_register_string('mbr-cookie-consent', $name, $value);
            }
        }
        
        // Register with Polylang.
        if (function_exists('pll_register_string')) {
            foreach ($strings as $name => $value) {
                pll_register_string($name, $value, 'MBR Cookie Consent');
            }
        }
        
        // Register category strings.
        $categories = get_option('mbr_cc_cookie_categories', array());
        
        if (!is_array($categories)) {
            $categories = array();
        }
        
        foreach ($categories as $slug => $category) {
            // Same guard as the settings save: a malformed entry must not take
            // the page down with a TypeError.
            if (!is_array($category)) {
                continue;
            }
            
            $cat_name = "category_{$slug}_name";
            $cat_desc = "category_{$slug}_description";
            
            if (function_exists('icl_register_string')) {
                icl_register_string('mbr-cookie-consent', $cat_name, $category['name'] ?? '');
                icl_register_string('mbr-cookie-consent', $cat_desc, $category['description'] ?? '');
            }
            
            if (function_exists('pll_register_string')) {
                pll_register_string($cat_name, $category['name'] ?? '', 'MBR Cookie Consent');
                pll_register_string($cat_desc, $category['description'] ?? '', 'MBR Cookie Consent');
            }
        }
    }
    
    /**
     * Get translated string (WPML/Polylang compatible).
     *
     * @param string $name String name.
     * @param string $original Original text.
     * @return string Translated text.
     */
    public static function get_translated_string($name, $original) {
        // In the site's default language the plugin's own setting is
        // authoritative and must win.
        //
        // Without this, icl_t() returns whatever was registered under $name
        // when the string was first seen and ignores the updated $original —
        // so editing the button text in the plugin appears to do nothing, and
        // the only way to change it is through WPML's String Translation
        // screen. That is a confusing failure: the setting saves correctly,
        // the admin field shows the new value, and the front end silently
        // keeps the old one.
        //
        // Translations for other languages still come from WPML or Polylang,
        // which is the behaviour multilingual sites actually want.
        if (self::is_default_language()) {
            return $original;
        }
        
        // WPML.
        if (function_exists('icl_t')) {
            return icl_t('mbr-cookie-consent', $name, $original);
        }
        
        // Polylang.
        if (function_exists('pll__')) {
            return pll__($original);
        }
        
        return $original;
    }
    
    /**
     * Is the request being served in the site's default language?
     *
     * Returns true when no multilingual plugin is active, since a
     * single-language site is always in its default language.
     *
     * @return bool
     */
    public static function is_default_language() {
        // WPML.
        if (defined('ICL_SITEPRESS_VERSION') || function_exists('icl_t')) {
            $current = apply_filters('wpml_current_language', null);
            $default = apply_filters('wpml_default_language', null);
            
            if ($current && $default) {
                return $current === $default;
            }
            
            // Could not determine — do not claim default, let WPML answer.
            return false;
        }
        
        // Polylang.
        if (function_exists('pll_current_language') && function_exists('pll_default_language')) {
            $current = pll_current_language();
            $default = pll_default_language();
            
            if ($current && $default) {
                return $current === $default;
            }
            
            return false;
        }
        
        return true;
    }
    
    /**
     * Add ARIA live region for screen reader announcements.
     */
    public function add_accessibility_announcements() {
        if (!get_option('mbr_cc_wcag_compliance', true)) {
            return;
        }
        
        ?>
        <!-- MBR Cookie Consent - Screen Reader Announcements -->
        <div id="mbr-cc-sr-announce" class="mbr-cc-sr-only" aria-live="polite" aria-atomic="true"></div>
        <?php
    }
    
    /**
     * Get supported languages.
     *
     * @return array Languages.
     */
    public static function get_supported_languages() {
        return self::$supported_languages;
    }
    
    /**
     * Check if multilingual plugin is active.
     *
     * @return string|false Plugin name or false.
     */
    public static function get_active_multilingual_plugin() {
        if (function_exists('icl_get_current_language')) {
            return 'WPML';
        }
        
        if (function_exists('pll_current_language')) {
            return 'Polylang';
        }
        
        return false;
    }
}
