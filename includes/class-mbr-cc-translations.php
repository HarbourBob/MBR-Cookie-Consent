<?php
/**
 * Community translations for the banner and preference centre.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Serves community-contributed banner translations to the browser.
 *
 * Two constraints shape this class.
 *
 * The first is caching. Choosing a language on the server from the visitor's
 * Accept-Language header would write one visitor's language into a document
 * that a page cache then serves to everyone — the same fault that made consent
 * state leak between visitors before 2.3.3. So the page is always rendered in
 * the site's own wording, identical for every visitor, and the swap happens in
 * the browser.
 *
 * The second is that these are consent notices, which is to say legal text.
 * The translations are community-contributed and unverified, and nobody here
 * can vouch for the Korean or the Arabic. Nothing is therefore served until an
 * administrator has read that language and marked it reviewed. An unreviewed
 * language falls back to the site's own wording, so the failure mode is text
 * the owner wrote rather than text nobody has checked.
 */
class MBR_CC_Translations {

    /**
     * Singleton instance.
     *
     * @var MBR_CC_Translations|null
     */
    private static $instance = null;

    /**
     * Every string the banner can display, with the plugin's own default.
     *
     * A string whose stored value differs from the default here has been
     * rewritten by the site owner. There is no community translation of their
     * wording, so it is left alone — showing their actual words in English is
     * better than showing a generic sentence in French that says something
     * subtly different.
     *
     * @return array<string,string>
     */
    public static function default_strings() {
        return array(
            'banner_heading'          => 'We value your privacy',
            'banner_description'      => 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.',
            'accept_all'              => 'Accept All',
            'reject_all'              => 'Reject All',
            'customize'               => 'Customize',
            'cookie_settings'         => 'Cookie Settings',
            'manage_preferences'      => 'Manage Cookie Preferences',
            'preferences_intro'       => 'We use cookies to enhance your experience. You can choose which types of cookies to allow.',
            'save_preferences'        => 'Save Preferences',
            'close'                   => 'Close preferences dialog',
            'necessary'               => 'Necessary',
            'analytics'               => 'Analytics',
            'marketing'               => 'Marketing',
            'preferences'             => 'Preferences',
            'always_active'           => 'Always Active',
            'privacy_policy_text'     => 'Privacy Policy',
            'cookie_policy_text'      => 'Cookie Policy',
            'ccpa_link_text'          => 'Do Not Sell or Share My Personal Information',
            'necessary_description'   => 'Necessary cookies are essential for the website to function properly. These cookies ensure basic functionalities and security features of the website.',
            'analytics_description'   => 'Analytics cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.',
            'marketing_description'   => 'Marketing cookies are used to track visitors across websites to display relevant advertisements and encourage user engagement.',
            'preferences_description' => 'Preference cookies enable a website to remember information that changes the way the website behaves or looks.',
            'blocked_heading'         => 'Content blocked',
            'blocked_message'         => 'This video is blocked because marketing cookies have not been accepted.',
            'blocked_button'          => 'Accept cookies & play video',
            'blocked_region_label'    => 'Blocked content',
            'form_blocked_message'    => 'Please accept cookies before submitting this form.',
        );
    }

    /**
     * Option holding each string's current value, keyed the same way.
     *
     * @return array<string,string>
     */
    private static function option_map() {
        return array(
            'banner_heading'      => 'mbr_cc_banner_heading',
            'banner_description'  => 'mbr_cc_banner_description',
            'accept_all'          => 'mbr_cc_accept_button_text',
            'reject_all'          => 'mbr_cc_reject_button_text',
            'customize'           => 'mbr_cc_customize_button_text',
            'cookie_settings'     => 'mbr_cc_revisit_consent_text',
            'privacy_policy_text' => 'mbr_cc_privacy_policy_text',
            'cookie_policy_text'  => 'mbr_cc_cookie_policy_text',
            'ccpa_link_text'      => 'mbr_cc_ccpa_link_text',
        );
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue'), 15);
    }

    /**
     * Languages an administrator has read and approved.
     *
     * @return string[]
     */
    public static function approved_languages() {
        $approved = get_option('mbr_cc_approved_languages', array());
        return is_array($approved) ? array_values(array_filter(array_map('sanitize_key', $approved))) : array();
    }

    /**
     * Whether automatic translation is switched on at all.
     *
     * @return bool
     */
    public static function is_enabled() {
        return (bool) get_option('mbr_cc_auto_translate', false);
    }

    /**
     * Read the shipped catalogue for one language.
     *
     * @param string $lang Two-letter code.
     * @return array<string,string> Strings, or empty if unavailable.
     */
    public static function catalogue($lang) {
        $lang = sanitize_key($lang);
        $file = MBR_CC_PLUGIN_DIR . 'languages/banner/' . $lang . '.json';

        // sanitize_key leaves no separators, but the concatenation is checked
        // against the intended directory regardless.
        $real = realpath($file);
        $base = realpath(MBR_CC_PLUGIN_DIR . 'languages/banner');

        if (!$real || !$base || strpos($real, $base) !== 0) {
            return array();
        }

        $raw = file_get_contents($real);
        if (false === $raw) {
            return array();
        }

        $data = json_decode($raw, true);
        return (is_array($data) && isset($data['strings']) && is_array($data['strings']))
            ? $data['strings']
            : array();
    }

    /**
     * The list of available languages and their coverage.
     *
     * @return array
     */
    public static function index() {
        $file = MBR_CC_PLUGIN_DIR . 'languages/banner/index.json';

        if (!file_exists($file)) {
            return array('total_keys' => 0, 'languages' => array());
        }

        $data = json_decode((string) file_get_contents($file), true);
        return is_array($data) ? $data : array('total_keys' => 0, 'languages' => array());
    }

    /**
     * Keys the site owner has rewritten, which must not be auto-translated.
     *
     * @return string[]
     */
    public static function customised_keys() {
        $defaults = self::default_strings();
        $custom   = array();

        foreach (self::option_map() as $key => $option) {
            $stored = get_option($option, null);

            if (null === $stored || '' === $stored) {
                continue;
            }

            if (isset($defaults[$key]) && $stored !== $defaults[$key]) {
                $custom[] = $key;
            }
        }

        return $custom;
    }

    /**
     * Hand the browser what it needs to swap languages.
     *
     * Every value here derives from site settings, so the payload is the same
     * for every visitor and safe to cache alongside the page.
     *
     * @return void
     */
    public function enqueue() {
        if (!self::is_enabled()) {
            return;
        }

        $approved = self::approved_languages();

        if (empty($approved)) {
            return;
        }

        wp_enqueue_script(
            'mbr-cc-translations',
            MBR_CC_PLUGIN_URL . 'assets/js/translations.js',
            array(),
            MBR_CC_VERSION,
            false // Head, so the swap happens before the banner is shown.
        );

        $index = self::index();
        $langs = array();

        foreach ($approved as $code) {
            if (isset($index['languages'][$code])) {
                $langs[$code] = array(
                    'direction' => $index['languages'][$code]['direction'] ?? 'ltr',
                );
            }
        }

        wp_localize_script('mbr-cc-translations', 'mbrCcI18n', array(
            'baseUrl'   => MBR_CC_PLUGIN_URL . 'languages/banner/',
            'version'   => MBR_CC_VERSION,
            'languages' => $langs,
            'skip'      => self::customised_keys(),
        ));
    }
}
