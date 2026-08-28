<?php
/**
 * Global Privacy Control (GPC) Handler
 *
 * Detects and honours the GPC browser signal (Sec-GPC header / navigator.globalPrivacyControl).
 *
 * As of 2026, GPC must be honoured as a valid opt-out signal in: California,
 * Colorado, Connecticut, Delaware, Maryland (effective 1 Oct 2025), Minnesota,
 * Montana, Nebraska, New Hampshire, New Jersey, Oregon, and Texas — with
 * additional states recognising it through universal opt-out preference signal
 * (UOOM) requirements each year. Treating GPC as a global opt-out is therefore
 * the safest US-wide posture.
 *
 * California CCPA regulations effective 1 January 2026 additionally require
 * visible confirmation when an opt-out request — including a GPC signal — is
 * processed; this is delivered via the showGpcConfirmation() toast in banner.js.
 *
 * When a GPC signal is detected, the handler automatically treats the visitor
 * as having opted out of data selling, sharing, and targeted advertising.
 * Marketing/analytics cookies that fall under "sale or sharing" definitions
 * are suppressed without requiring the visitor to interact with the consent
 * banner.
 *
 * @package MBR_Cookie_Consent
 * @version 2.1.0
 * @since 2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_GPC_Handler {

    /**
     * Singleton instance.
     *
     * @var MBR_CC_GPC_Handler
     */
    private static $instance = null;

    /**
     * Whether a GPC signal was detected on this request.
     *
     * @var bool
     */
    private $gpc_detected = false;

    /**
     * Whether GPC handling is enabled in settings.
     *
     * @var bool
     */
    private $enabled = false;

    /**
     * Get singleton instance.
     *
     * @return MBR_CC_GPC_Handler
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
        $this->enabled = (bool) get_option('mbr_cc_gpc_enabled', true);

        if (!$this->enabled) {
            return;
        }

        // Detect server-side GPC header. Retained for is_gpc_active(), which
        // integrations may call during a request that is not being cached.
        // Nothing rendered into the page may depend on it — see below.
        $this->detect_gpc_signal();

        // Pass GPC state to frontend scripts.
        add_action('wp_enqueue_scripts', array($this, 'localize_gpc_state'), 20);

        // Note: this class used to register mbr_cc_has_category_consent and
        // mbr_cc_consent_log_data filters as a "server-side backstop". Neither
        // filter was ever applied anywhere in the plugin, so the backstop did
        // nothing — and it could not have worked in any case. Enforcing GPC on
        // the server means varying the page by a request header, which on a
        // cached site writes one visitor's signal into the copy served to
        // everybody. GPC is honoured in the browser, where it belongs.
        //
        // 2.3.4 removed the last of that: apply_gpc_overrides() was still
        // hooked to mbr_cc_banner_config and still set enable_ccpa, which is
        // rendered into the document. One visitor sending Sec-GPC from a US
        // address primed the cache with the "Do Not Sell or Share" link
        // showing, and every subsequent visitor was served it. Harmless in
        // effect — it over-discloses rather than under-discloses — but it was
        // the same defect as the one this class had already been fixed for
        // once, sitting two methods further down. The link is now revealed in
        // the browser by banner.js, from the region the visitor actually
        // resolved to.
    }

    /**
     * Detect the GPC signal from the Sec-GPC HTTP header.
     *
     * Browsers that support GPC (Firefox, Brave, DuckDuckGo, and extensions like
     * Privacy Badger) send Sec-GPC: 1 with every request.
     */
    private function detect_gpc_signal() {
        // Check for the Sec-GPC header.
        // PHP normalises it to HTTP_SEC_GPC in $_SERVER.
        if (isset($_SERVER['HTTP_SEC_GPC']) && $_SERVER['HTTP_SEC_GPC'] === '1') {
            $this->gpc_detected = true;
            return;
        }

        // Some proxies or CDNs may strip the header but pass it differently.
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Sec-GPC']) && $headers['Sec-GPC'] === '1') {
                $this->gpc_detected = true;
            }
        }
    }

    /**
     * Check whether a GPC signal is active for the current request.
     *
     * @return bool
     */
    public function is_gpc_active() {
        return $this->enabled && $this->gpc_detected;
    }

    /**
     * Pass GPC detection state to the frontend.
     *
     * The banner.js script uses this to show the "Opt-Out Request Honored"
     * confirmation and to automatically suppress marketing/analytics consent
     * on the client side.
     */
    public function localize_gpc_state() {
        if (is_admin()) {
            return;
        }

        // Attach GPC state to the existing banner script data.
        // serverDetected is deliberately not sent.
        //
        // It carried the Sec-GPC header from whichever visitor happened to
        // generate the page. On a cached site that meant a single visitor with
        // GPC enabled could prime the cache with serverDetected: true, after
        // which every visitor was treated as having sent the signal and
        // marketing consent was suppressed site-wide — silently, and with
        // nothing in the interface to explain it.
        //
        // The signal is read from navigator.globalPrivacyControl instead, which
        // the GPC specification requires alongside the header and which is
        // per-visitor by definition.
        wp_localize_script('mbr-cc-banner', 'mbrCcGpc', array(
            'enabled'                => $this->enabled,
            'honoredMessage'         => get_option(
                'mbr_cc_gpc_honored_message',
                'Opt-Out Request Honored'
            ),
            'showHonoredConfirmation' => (bool) get_option('mbr_cc_gpc_show_confirmation', true),
            'suppressCategories'     => $this->get_suppressed_categories(),
        ));
    }

    /**
     * Get the cookie categories that should be suppressed when GPC is active.
     *
     * By default, marketing and analytics (where analytics constitutes "sharing")
     * are suppressed. Site owners can customise this via a filter or the admin setting.
     *
     * @return array Category slugs to suppress.
     */
    private function get_suppressed_categories() {
        $defaults = array('marketing');

        // Allow site owners to also suppress analytics if their analytics
        // setup constitutes "sale or sharing" under CCPA/state law.
        if (get_option('mbr_cc_gpc_suppress_analytics', false)) {
            $defaults[] = 'analytics';
        }

        /**
         * Filter the categories suppressed by GPC.
         *
         * @since 2.0.0
         * @param array $categories Category slugs to suppress.
         */
        return apply_filters('mbr_cc_gpc_suppressed_categories', $defaults);
    }

    /**
     * Filter category consent checks server-side.
     *
     * When GPC is active, marketing consent is forced to false regardless of
     * what the cookie says. This provides a server-side backstop.
     *
     * @param bool   $has_consent Current consent state.
     * @param string $category    Category slug being checked.
     * @return bool  Filtered consent state.
     */
    public function filter_category_consent($has_consent, $category) {
        if (!$this->gpc_detected) {
            return $has_consent;
        }

        $suppressed = $this->get_suppressed_categories();
        if (in_array($category, $suppressed, true)) {
            return false;
        }

        return $has_consent;
    }

    /**
     * Append GPC detection status to consent log entries.
     *
     * @param array $log_data Consent log data.
     * @return array Modified log data.
     */
    public function append_gpc_to_log($log_data) {
        $log_data['gpc_detected'] = $this->gpc_detected;
        return $log_data;
    }

    /**
     * Check if GPC handling is enabled.
     *
     * @return bool
     */
    public function is_enabled() {
        return $this->enabled;
    }
}

/**
 * Helper function to get GPC handler instance.
 *
 * @return MBR_CC_GPC_Handler
 */
function mbr_cc_gpc() {
    return MBR_CC_GPC_Handler::get_instance();
}
