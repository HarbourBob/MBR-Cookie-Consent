<?php
/**
 * Admin interface handler.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Admin class.
 */
class MBR_CC_Admin {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Admin
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Admin
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
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_notices', array($this, 'render_upgrade_notices'));
        add_action('admin_post_mbr_cc_dismiss_notice', array($this, 'handle_dismiss_notice'));
        add_action('admin_post_mbr_cc_translation_review', array($this, 'handle_translation_review'));
        
        // AJAX handlers.
        add_action('wp_ajax_mbr_cc_export_logs', array($this, 'ajax_export_logs'));
        add_action('wp_ajax_mbr_cc_delete_logs', array($this, 'ajax_delete_logs'));
        add_action('wp_ajax_mbr_cc_save_form_settings', array($this, 'ajax_save_form_settings'));
        add_action('wp_ajax_mbr_cc_save_ab_enabled', array($this, 'ajax_save_ab_enabled'));
    }
    
    /**
     * Surface one-time notices left behind by upgrade routines.
     *
     * Migrations that change behaviour should say so rather than happening
     * silently, so each sets an option flag which is cleared when dismissed.
     */
    public function render_upgrade_notices() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $notices = array();
        
        if (get_option('mbr_cc_231_geo_provider_switched')) {
            $notices['mbr_cc_231_geo_provider_switched'] = sprintf(
                /* translators: %s: settings screen URL. */
                __('MBR Cookie Consent has switched your IP lookup provider from ip-api.com to ipapi.co. ip-api.com does not offer HTTPS on its free tier, and lookups over plain HTTP can be altered in transit to change which privacy regime your visitors are shown. You can review this on the %s screen.', 'mbr-cookie-consent'),
                '<a href="' . esc_url(admin_url('admin.php?page=mbr-cookie-consent-settings#tab-geolocation')) . '">' . esc_html__('Geolocation settings', 'mbr-cookie-consent') . '</a>'
            );
        }
        
        if (get_option('mbr_cc_233_tcf_withdrawn')) {
            $notices['mbr_cc_233_tcf_withdrawn'] = __('MBR Cookie Consent has withdrawn its IAB TCF option, which you had switched on. The feature was never functional: it sent every vendor the same fixed consent string regardless of what your visitors had chosen, and reported a CMP ID of zero, so no vendor could act on it correctly. Implementing TCF properly requires registration with IAB Europe, which this plugin does not have. If you need TCF, you will need a registered CMP. If you generated a privacy policy while the option was enabled, regenerate it — it claimed TCF participation that was not accurate.', 'mbr-cookie-consent');
        }
        
        if (get_option('mbr_cc_234_acm_withdrawn')) {
            $notices['mbr_cc_234_acm_withdrawn'] = __('MBR Cookie Consent has withdrawn its Google Additional Consent Mode option, which you had switched on. The feature was never functional: no AC String was ever generated, stored or sent to Google, its provider list was a placeholder, and its JavaScript was never loaded. Switching it on only added an empty callback to your pages. Nothing on your site relied on it, so nothing will change — but if you use Google programmatic advertising and need Additional Consent Mode, you will need a consent platform that implements it. If you generated a privacy policy while the option was enabled, regenerate it: it may list Google Ad Manager as a service you use.', 'mbr-cookie-consent');
        }
        
        if (get_option('mbr_cc_232_multilingual_precedence')) {
            $notices['mbr_cc_232_multilingual_precedence'] = __('MBR Cookie Consent now takes precedence over WPML and Polylang for banner wording in your site\'s default language. Previously, editing the banner heading, description or button labels in this plugin had no effect on the front end once the strings had been registered with your translation plugin — the old wording was served instead. Translations for your other languages are unchanged and still come from WPML or Polylang. If you had worked around this by editing the default-language wording in String Translation, set it in Cookie Consent > Settings instead.', 'mbr-cookie-consent');
        }
        
        if (get_option('mbr_cc_230_default_region_preserved')) {
            $notices['mbr_cc_230_default_region_preserved'] = __('MBR Cookie Consent changed its shipped defaults for the "Rest of World" region to opt-in. Your existing settings have been preserved exactly as they were, so nothing on your site has changed.', 'mbr-cookie-consent');
        }
        
        foreach ($notices as $flag => $message) {
            $dismiss_url = wp_nonce_url(
                admin_url('admin-post.php?action=mbr_cc_dismiss_notice&notice=' . rawurlencode($flag)),
                'mbr_cc_dismiss_' . $flag
            );
            
            printf(
                '<div class="notice notice-info"><p>%s</p><p><a href="%s">%s</a></p></div>',
                wp_kses($message, array('a' => array('href' => array()))),
                esc_url($dismiss_url),
                esc_html__('Dismiss', 'mbr-cookie-consent')
            );
        }
    }
    
    /**
     * Clear a dismissed upgrade notice.
     */
    public function handle_dismiss_notice() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to perform this action.', 'mbr-cookie-consent'));
        }
        
        $notice = isset($_GET['notice']) ? sanitize_key(wp_unslash($_GET['notice'])) : '';
        
        $allowed = array(
            'mbr_cc_234_acm_withdrawn',
            'mbr_cc_233_tcf_withdrawn',
            'mbr_cc_232_multilingual_precedence',
            'mbr_cc_231_geo_provider_switched',
            'mbr_cc_230_default_region_preserved',
        );
        
        if (!in_array($notice, $allowed, true)) {
            wp_safe_redirect(admin_url());
            exit;
        }
        
        check_admin_referer('mbr_cc_dismiss_' . $notice);
        
        delete_option($notice);
        
        wp_safe_redirect(wp_get_referer() ? wp_get_referer() : admin_url());
        exit;
    }
    
    /**
     * Add admin menu pages.
     */
    public function add_admin_menu() {
        add_menu_page(
            __('Cookie Consent', 'mbr-cookie-consent'),
            __('Cookie Consent', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent',
            array($this, 'render_dashboard_page'),
            'dashicons-shield',
            30
        );
        
        add_submenu_page(
            'mbr-cookie-consent',
            __('Dashboard', 'mbr-cookie-consent'),
            __('Dashboard', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent',
            array($this, 'render_dashboard_page')
        );
        
        add_submenu_page(
            'mbr-cookie-consent',
            __('Settings', 'mbr-cookie-consent'),
            __('Settings', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-settings',
            array($this, 'render_settings_page')
        );
        
        add_submenu_page(
            'mbr-cookie-consent',
            __('Cookie Scanner', 'mbr-cookie-consent'),
            __('Cookie Scanner', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-scanner',
            array($this, 'render_scanner_page')
        );
        
        add_submenu_page(
            'mbr-cookie-consent',
            __('Consent Logs', 'mbr-cookie-consent'),
            __('Consent Logs', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-logs',
            array($this, 'render_logs_page')
        );
        
        add_submenu_page(
            'mbr-cookie-consent',
            __('Cookie Categories', 'mbr-cookie-consent'),
            __('Categories', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-categories',
            array($this, 'render_categories_page')
        );

        add_submenu_page(
            'mbr-cookie-consent',
            __('Form Integration', 'mbr-cookie-consent'),
            __('Form Integration', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-forms',
            array($this, 'render_forms_page')
        );

        add_submenu_page(
            'mbr-cookie-consent',
            __('A/B Testing', 'mbr-cookie-consent'),
            __('A/B Testing', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-ab-testing',
            array($this, 'render_ab_testing_page')
        );

        add_submenu_page(
            'mbr-cookie-consent',
            __('Translations', 'mbr-cookie-consent'),
            __('Translations', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-translations',
            array($this, 'render_translations_page')
        );

        add_submenu_page(
            'mbr-cookie-consent',
            __('Import / Export', 'mbr-cookie-consent'),
            __('Import / Export', 'mbr-cookie-consent'),
            'manage_options',
            'mbr-cookie-consent-import-export',
            array($this, 'render_import_export_page')
        );
    }
    
    /**
     * Render the community translations review screen.
     *
     * @return void
     */
    public function render_translations_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/translations.php';
    }

    /**
     * Approve or withdraw a community translation.
     *
     * Approval is deliberately a separate, explicit act rather than a settings
     * checkbox. These are consent notices in a language the site owner may not
     * read, and the confirmation on the review screen is the record that
     * somebody looked at the wording before it went out.
     *
     * @return void
     */
    public function handle_translation_review() {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to do that.', 'mbr-cookie-consent'));
        }

        check_admin_referer('mbr_cc_translation_review');

        $lang    = isset($_POST['lang']) ? sanitize_key(wp_unslash($_POST['lang'])) : '';
        $approve = isset($_POST['approve']) && '1' === wp_unslash($_POST['approve']);
        $index   = MBR_CC_Translations::index();

        if ('' === $lang || !isset($index['languages'][$lang])) {
            wp_safe_redirect(admin_url('admin.php?page=mbr-cookie-consent-translations'));
            exit;
        }

        // Approving without ticking the confirmation is refused outright. The
        // checkbox is the whole point of the screen.
        if ($approve && empty($_POST['confirm'])) {
            wp_safe_redirect(add_query_arg(
                array('page' => 'mbr-cookie-consent-translations', 'review' => $lang, 'mbr_cc_msg' => 'confirm'),
                admin_url('admin.php')
            ));
            exit;
        }

        $approved = MBR_CC_Translations::approved_languages();

        if ($approve) {
            $approved[] = $lang;
        } else {
            $approved = array_diff($approved, array($lang));
        }

        update_option('mbr_cc_approved_languages', array_values(array_unique($approved)));

        wp_safe_redirect(add_query_arg(
            array(
                'page'       => 'mbr-cookie-consent-translations',
                'mbr_cc_msg' => $approve ? 'approved' : 'withdrawn',
            ),
            admin_url('admin.php')
        ));
        exit;
    }

    /**
     * Enqueue admin assets.
     *
     * @param string $hook Current admin page hook.
     */
    public function enqueue_admin_assets($hook) {
        // Only load on plugin pages.
        if (strpos($hook, 'mbr-cookie-consent') === false) {
            return;
        }
        
        wp_enqueue_style(
            'mbr-cc-admin',
            MBR_CC_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            mbr_cc_asset_version('assets/css/admin.css')
        );
        
        wp_enqueue_script(
            'mbr-cc-admin',
            MBR_CC_PLUGIN_URL . 'assets/js/admin.js',
            array('jquery', 'wp-color-picker'),
            mbr_cc_asset_version('assets/js/admin.js'),
            true
        );
        
        // Always enqueue settings JS and media library on plugin pages
        wp_enqueue_media();
        wp_enqueue_script(
            'mbr-cc-admin-settings',
            MBR_CC_PLUGIN_URL . 'assets/js/admin-settings.js',
            array('jquery'),
            mbr_cc_asset_version('assets/js/admin-settings.js'),
            true
        );
        
        wp_enqueue_style('wp-color-picker');
        
        wp_localize_script('mbr-cc-admin', 'mbrCcAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('mbr_cc_admin_nonce'),
            'confirmDelete' => __('Are you sure you want to delete this item?', 'mbr-cookie-consent'),
            'confirmClear' => __('Are you sure you want to clear all blocked scripts?', 'mbr-cookie-consent'),
        ));
    }
    
    /**
     * Register settings.
     */
    public function register_settings() {
        // Settings will be registered via the Settings class.
    }
    
    /**
     * Render dashboard page.
     */
    public function render_dashboard_page() {
        $db = MBR_CC_Database::get_instance();
        $total_logs = $db->get_consent_count();
        $recent_logs = $db->get_consent_logs(array('limit' => 10));
        
        $blocker = MBR_CC_Script_Blocker::get_instance();
        $blocked_scripts = $blocker->get_blocked_scripts();
        
        $policy_page_id = MBR_CC_Policy_Generator::get_instance()->get_policy_page_id();
        
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/dashboard.php';
    }
    
    /**
     * Render settings page.
     */
    public function render_settings_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/settings.php';
    }
    
    /**
     * Render scanner page.
     */
    public function render_scanner_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/scanner.php';
    }
    
    /**
     * Render logs page.
     */
    public function render_logs_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/logs.php';
    }
    
    /**
     * Render categories page.
     */
    public function render_categories_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/categories.php';
    }

    public function render_forms_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/form-integration.php';
    }

    public function render_ab_testing_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/ab-testing.php';
    }

    /**
     * Render import/export page.
     */
    public function render_import_export_page() {
        require_once MBR_CC_PLUGIN_DIR . 'admin/views/import-export.php';
    }
    
    /**
     * AJAX: Export consent logs to CSV.
     */
    public function ajax_export_logs() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $db = MBR_CC_Database::get_instance();
        
        $args = array();
        
        if (isset($_GET['date_from']) && !empty($_GET['date_from'])) {
            $args['date_from'] = sanitize_text_field(wp_unslash($_GET['date_from']));
        }
        
        if (isset($_GET['date_to']) && !empty($_GET['date_to'])) {
            $args['date_to'] = sanitize_text_field(wp_unslash($_GET['date_to']));
        }
        
        $csv = $db->export_to_csv($args);
        
        if (empty($csv)) {
            wp_die('No logs to export');
        }
        
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="cookie-consent-logs-' . gmdate('Y-m-d') . '.csv"');
        header('Pragma: no-cache');
        header('Expires: 0');
        
        // CSV is generated internally from sanitized log data and streamed as a file attachment, not rendered as HTML; standard output escaping functions are not applicable to CSV content.
        echo $csv; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }
    
    /**
     * AJAX: Delete old consent logs.
     */
    public function ajax_delete_logs() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $days = isset($_POST['days']) ? absint($_POST['days']) : 365;
        
        // Refuse days < 1: strtotime("-0 days") resolves to "now", which
        // would delete every consent log in the table.
        if ($days < 1) {
            wp_send_json_error(array('message' => __('Days must be 1 or more.', 'mbr-cookie-consent')));
        }
        
        $db = MBR_CC_Database::get_instance();
        $deleted = $db->delete_old_logs($days);
        
        if ($deleted === false) {
            wp_send_json_error(array('message' => 'Failed to delete logs.'));
        }
        
        wp_send_json_success(array(
            /* translators: %d: number of consent log entries that were deleted. */
            'message' => sprintf(__('Deleted %d log entries.', 'mbr-cookie-consent'), $deleted),
            'deleted' => $deleted,
        ));
    }

    /**
     * AJAX: Save form integration settings.
     */
    public function ajax_save_form_settings() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        update_option('mbr_cc_form_integration_enabled', !empty($_POST['enabled']));
        update_option('mbr_cc_form_integration_message', sanitize_text_field(wp_unslash($_POST['message'] ?? '')));
        update_option('mbr_cc_form_required_category', sanitize_key(wp_unslash($_POST['category'] ?? 'necessary')));

        if (class_exists('MBR_CC_Cache')) {
            MBR_CC_Cache::flush('form_integration');
        }

        wp_send_json_success(array('message' => __('Form integration settings saved.', 'mbr-cookie-consent')));
    }

    /**
     * AJAX: Save A/B testing enabled state.
     */
    public function ajax_save_ab_enabled() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        update_option('mbr_cc_ab_testing_enabled', !empty($_POST['enabled']));

        if (class_exists('MBR_CC_Cache')) {
            MBR_CC_Cache::flush('ab_testing');
        }

        wp_send_json_success(array('message' => __('A/B testing setting saved.', 'mbr-cookie-consent')));
    }
}
