<?php
/**
 * Consent Manager - handles user consent actions.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consent Manager class.
 */
class MBR_CC_Consent_Manager {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Consent_Manager
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Consent_Manager
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
        // AJAX handlers for consent actions.
        add_action('wp_ajax_mbr_cc_save_consent', array($this, 'ajax_save_consent'));
        add_action('wp_ajax_nopriv_mbr_cc_save_consent', array($this, 'ajax_save_consent'));
        
        add_action('wp_ajax_mbr_cc_get_consent', array($this, 'ajax_get_consent'));
        add_action('wp_ajax_nopriv_mbr_cc_get_consent', array($this, 'ajax_get_consent'));
        
        add_action('wp_ajax_mbr_cc_revoke_consent', array($this, 'ajax_revoke_consent'));
        add_action('wp_ajax_nopriv_mbr_cc_revoke_consent', array($this, 'ajax_revoke_consent'));
        
        // Enqueue frontend scripts.
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));
    }
    
    /**
     * Enqueue consent management scripts.
     */
    public function enqueue_scripts() {
        // Don't load on admin pages.
        if (is_admin()) {
            return;
        }
        
        wp_enqueue_script(
            'mbr-cc-consent',
            MBR_CC_PLUGIN_URL . 'assets/js/consent-manager.js',
            array('jquery'),
            MBR_CC_VERSION,
            true
        );
        
        wp_localize_script('mbr-cc-consent', 'mbrCcConsent', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mbr_cc_consent_nonce'),
            'cookieName' => 'mbr_cc_consent',
            'cookieExpiry' => (int) get_option('mbr_cc_cookie_expiry_days', 365),
            'reloadOnConsent' => (bool) get_option('mbr_cc_reload_on_consent', false),
            'cookieDomain' => apply_filters('mbr_cc_cookie_domain', ''),
            'cookiePath' => apply_filters('mbr_cc_cookie_path', '/'),
            'queueKey' => 'mbr_cc_log_queue_' . get_current_blog_id() . '_' . substr(md5(home_url('/')), 0, 12),
            // Deliberately narrow: never clear all cookies or browser storage.
            // Integrators may add reviewed service-specific names. A trailing
            // * denotes a prefix; required categories are excluded in JS.
            'cleanupRules' => apply_filters('mbr_cc_withdrawal_cleanup_rules', array(
                'analytics' => array('cookies' => array('_ga', '_ga_*', '_gid', '_gat', '_gat_*'), 'localStorage' => array(), 'sessionStorage' => array()),
                'marketing' => array('cookies' => array('_gcl_au', '_gcl_aw', '_gcl_dc', '_fbp', '_fbc', '_uetvid', '_uetsid'), 'localStorage' => array('_uetvid', '_uetvid_exp', '_uetsid', '_uetsid_exp'), 'sessionStorage' => array()),
            )),
        ));
    }
    
    /**
     * AJAX: Save user consent.
     */
    public function ajax_save_consent() {
        // Use a soft nonce check rather than check_ajax_referer() which dies on
        // failure. On cached sites the nonce baked into the page HTML can be
        // stale (caches persist beyond the 12-hour nonce lifetime). A failed
        // nonce must not block the consent log — the cookie is already set in JS
        // regardless of whether this AJAX call succeeds.
        //
        // Because the nonce is advisory, this endpoint is effectively open, so
        // the throttle below — not the nonce — is what stops the consent log
        // being flooded with junk rows.
        $nonce_valid = isset( $_POST['nonce'] ) && is_string($_POST['nonce']) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'mbr_cc_consent_nonce' );
        if ( ! $nonce_valid ) {
            $this->log_stale_nonce();
        }
        
        $raw_consent = isset($_POST['consent']) ? wp_unslash($_POST['consent']) : '';
        
        // A genuine consent payload is a small flat object. Anything larger is
        // not from our banner, and decoding it just wastes memory.
        if (!is_string($raw_consent) || strlen($raw_consent) > 2048) {
            wp_send_json_error(array('message' => __('Invalid consent data.', 'mbr-cookie-consent')));
        }
        
        $consent_data = json_decode($raw_consent, true);
        $consent_method = isset($_POST['method']) && is_string($_POST['method']) ? sanitize_text_field(wp_unslash($_POST['method'])) : 'banner';
        
        // Anything outside this list is recorded as 'other' rather than written
        // to the log verbatim.
        //
        // The first three are what banner.js sends. They were missing, so every
        // row logged since the allowlist was introduced recorded 'other' and the
        // Method column — in a log kept specifically as evidence of how consent
        // was obtained — held nothing of use. The remainder cover programmatic
        // and integration paths.
        $allowed_methods = array(
            'accept_all',
            'reject_all',
            'preferences',
            'banner',
            'settings',
            'revoked',
            'gpc',
            'api',
            'form',
            'auto',
        );
        if (!in_array($consent_method, $allowed_methods, true)) {
            $consent_method = 'other';
        }
        
        if (empty($consent_data) || !is_array($consent_data)) {
            wp_send_json_error(array('message' => __('Invalid consent data.', 'mbr-cookie-consent')));
        }
        
        // Determine if consent was given.
        $consent_given = false;
        $categories_accepted = array();
        
        // Category names are attacker-supplied JSON keys, so they are matched
        // against the categories this site has actually registered rather than
        // stored as given. Without this an arbitrary string reaches the consent
        // log and, from there, the admin's CSV export.
        $known_categories = $this->get_known_category_slugs();
        
        if (isset($consent_data['all']) && $consent_data['all'] === true) {
            $consent_given = true;
            $categories_accepted = $known_categories;
        } elseif (isset($consent_data['necessary']) && $consent_data['necessary'] === true) {
            $consent_given = true;
            
            // Collect accepted categories.
            foreach ($consent_data as $category => $accepted) {
                if ($accepted !== true) {
                    continue;
                }
                
                $slug = sanitize_key($category);
                
                if ($slug !== '' && in_array($slug, $known_categories, true)) {
                    $categories_accepted[] = $slug;
                }
            }
            
            $categories_accepted = array_values(array_unique($categories_accepted));
        }
        
        // A rejection/withdrawal is not optional consent, even though the
        // necessary category remains enabled. Preserve categories separately.
        $consent_given = count(array_diff($categories_accepted, array('necessary'))) > 0;
        if ($consent_method === 'revoked') {
            $consent_given = false;
            $categories_accepted = array('necessary');
        }

        // Event IDs are not credentials or visitor IDs. Include the validated
        // payload in the key so reusing an ID with different choices cannot
        // suppress a different decision. Legacy clients without IDs still work.
        $event_id = isset($_POST['event_id']) ? wp_unslash($_POST['event_id']) : '';
        if (!is_string($event_id) || ($event_id !== '' && !preg_match('/^[a-f0-9]{32}$/D', $event_id))) {
            wp_send_json_error(array('message' => 'Invalid event ID.', 'retryable' => false), 400);
        }
        sort($categories_accepted);
        $event_key = $event_id === '' ? null : hash_hmac('sha256',
            $event_id . '|' . $consent_method . '|' . wp_json_encode($categories_accepted), wp_salt('auth'));
        $db = MBR_CC_Database::get_instance();
        if ($event_key && $db->find_event($event_key)) {
            wp_send_json_success(array('recorded' => true, 'duplicate' => true));
        }
        if (!$this->can_log_consent()) {
            $this->record_log_health('throttled');
            wp_send_json_error(array('message' => 'Consent log rate limit reached.', 'recorded' => false, 'retryable' => false), 429);
        }
        $log_id = $db->log_consent(array(
            'consent_given' => $consent_given,
            'categories_accepted' => $categories_accepted,
            'consent_method' => $consent_method,
            'event_key' => $event_key,
        ));
        
        if ($log_id) {
            $this->record_log_health('success');
            wp_send_json_success(array(
                'message' => 'Consent saved successfully.',
                'recorded' => true,
            ));
        } else {
            $this->record_log_health('database_error');
            wp_send_json_error(array('message' => 'Failed to save consent.', 'recorded' => false, 'retryable' => true), 503);
        }
    }

    /** Bounded diagnostic metadata, with no visitor identifiers or DB errors. */
    private function record_log_health($status) {
        $key = $status === 'success' ? 'mbr_cc_log_last_success' : 'mbr_cc_log_last_failure';
        $previous = get_option($key, array());
        // Limit extra option writes during traffic bursts; retain failures even
        // after a later success so administrators can see intermittent issues.
        if (!is_array($previous) || empty($previous['time']) || time() - (int) $previous['time'] >= 60) {
            update_option($key, array('time' => time(), 'status' => $status), false);
        }
    }
    
    /**
     * Registered category slugs, plus the fixed set the banner always sends.
     *
     * @return array Category slugs.
     */
    private function get_known_category_slugs() {
        $slugs = array('necessary', 'analytics', 'marketing', 'preferences');
        
        $categories = $this->get_categories();
        
        if (is_array($categories)) {
            foreach (array_keys($categories) as $slug) {
                $slug = sanitize_key($slug);
                
                if ($slug !== '') {
                    $slugs[] = $slug;
                }
            }
        }
        
        return array_values(array_unique($slugs));
    }
    
    /**
     * Throttle consent log writes per visitor.
     *
     * This endpoint accepts unauthenticated requests by design, so without a
     * throttle anyone can append unlimited rows to the consent log — bloating
     * the table and, more importantly, devaluing the one record a site would
     * rely on to demonstrate compliance.
     *
     * A real visitor writes once on their initial choice and occasionally again
     * when changing preferences, so the allowance is deliberately generous.
     *
     * @return bool True if this request may write a log row.
     */
    private function can_log_consent() {
        /**
         * Filter how many consent log writes a single visitor may make.
         *
         * @since 2.3.1
         *
         * @param int $limit  Maximum writes per window. 0 disables throttling.
         * @param int $window Window length in seconds.
         */
        $limit  = (int) apply_filters('mbr_cc_consent_log_limit', 10);
        $window = (int) apply_filters('mbr_cc_consent_log_window', 600);
        
        if ($limit <= 0) {
            return true;
        }
        
        $ip = function_exists('mbr_cc_get_client_ip') ? mbr_cc_get_client_ip() : '';
        
        if ($ip === '') {
            // No usable identity to throttle on. Allow the write rather than
            // silently dropping consent records on an oddly configured host.
            return true;
        }
        
        // Hashed with the site's auth salt so the transient name is not itself
        // a plaintext record of who visited.
        $key   = 'mbr_cc_cw_' . hash('sha256', $ip . wp_salt('auth'));
        $count = (int) get_transient($key);
        
        if ($count >= $limit) {
            return false;
        }
        
        set_transient($key, $count + 1, $window);
        
        return true;
    }
    
    /**
     * Record a stale-nonce occurrence without flooding the PHP error log.
     *
     * The original unconditional error_log() call turned any traffic spike, or
     * any attacker hitting the endpoint in a loop, into unbounded disk writes.
     * One line an hour is enough to notice a genuine caching problem.
     */
    private function log_stale_nonce() {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }
        
        if (get_transient('mbr_cc_stale_nonce_logged')) {
            return;
        }
        
        set_transient('mbr_cc_stale_nonce_logged', 1, HOUR_IN_SECONDS);
        
        // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        error_log('MBR Cookie Consent: stale or missing nonce on consent save (further occurrences suppressed for one hour). Consent cookie is set client-side regardless.');
    }
    
    /**
     * AJAX: Get current consent.
     */
    public function ajax_get_consent() {
        check_ajax_referer('mbr_cc_consent_nonce', 'nonce');
        
        $consent = $this->get_user_consent();
        
        wp_send_json_success(array(
            'consent' => $consent,
            'hasConsent' => !empty($consent),
        ));
    }
    
    /**
     * AJAX: Revoke consent.
     */
    public function ajax_revoke_consent() {
        // Same validation, throttling, idempotency and honest result as saves.
        // This endpoint only records the action; the browser owns its choice.
        $_POST['consent'] = '{"necessary":true}';
        $_POST['method'] = 'revoked';
        $this->ajax_save_consent();
    }
    
    /**
     * Get user consent from cookie.
     *
     * @return array Consent preferences.
     */
    public function get_user_consent() {
        if (!isset($_COOKIE['mbr_cc_consent'])) {
            return array();
        }
        
        $raw = wp_unslash($_COOKIE['mbr_cc_consent']);
        
        // The cookie is client-controlled and this runs on every page load, so
        // an oversized value is rejected before it reaches json_decode().
        if (!is_string($raw) || $raw === '' || strlen($raw) > 2048) {
            return array();
        }
        
        $consent = json_decode($raw, true);
        
        return is_array($consent) ? $consent : array();
    }
    
    /**
     * Check if user has given consent.
     *
     * @return bool Has consent.
     */
    public function has_consent() {
        return !empty($this->get_user_consent());
    }
    
    /**
     * Check if user consented to specific category.
     *
     * @param string $category Category slug.
     * @return bool Has category consent.
     */
    public function has_category_consent($category) {
        $consent = $this->get_user_consent();
        
        // Check for "accept all".
        if (isset($consent['all']) && $consent['all'] === true) {
            return true;
        }
        
        // Check specific category.
        return isset($consent[$category]) && $consent[$category] === true;
    }
    
    /**
     * Get cookie categories.
     *
     * @return array Categories.
     */
    public function get_categories() {
        return get_option('mbr_cc_cookie_categories', array());
    }
    
    /**
     * Update cookie categories.
     *
     * @param array $categories Categories data.
     * @return bool Success.
     */
    public function update_categories($categories) {
        return update_option('mbr_cc_cookie_categories', $categories);
    }
    
    /**
     * Get category by slug.
     *
     * @param string $slug Category slug.
     * @return array|false Category data or false.
     */
    public function get_category($slug) {
        $categories = $this->get_categories();
        return isset($categories[$slug]) ? $categories[$slug] : false;
    }
}
