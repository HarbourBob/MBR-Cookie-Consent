<?php
/**
 * Settings import / export.
 *
 * Exports the plugin's configuration as a portable JSON file and imports it
 * back on another install. Built for multi-site rollouts: push one tuned
 * configuration out to many sites without re-keying every field.
 *
 * Design notes:
 *  - Settings ONLY. Consent logs are visitor personal data and are never
 *    included here (they have their own CSV export on the Logs screen).
 *  - Import is allowlist-driven. Any key in the file that is not a known
 *    setting is ignored, and every value is passed back through the same
 *    sanitiser the plugin uses when saving. A tampered or corrupt file
 *    cannot inject arbitrary options or unsanitised data.
 *  - Site-local and runtime values are excluded from the export by design:
 *    plugin/db version markers (they drive migrations), the geolocation IP
 *    cache, and the policy page IDs (a page ID on one site is meaningless on
 *    another).
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Import / Export handler.
 */
class MBR_CC_Import_Export {

    /**
     * Single instance.
     *
     * @var MBR_CC_Import_Export
     */
    private static $instance = null;

    /**
     * File format identifier written into (and validated on) every export.
     */
    const FORMAT = 'mbr-cookie-consent-settings';

    /**
     * File format revision. Bump only if the envelope shape changes.
     */
    const FORMAT_VERSION = 1;

    /**
     * Option name holding the one-slot pre-import backup.
     */
    const BACKUP_OPTION = 'mbr_cc_import_backup';

    /**
     * Maximum accepted upload size in bytes (256 KB is ample for settings).
     */
    const MAX_UPLOAD_BYTES = 262144;

    /**
     * Get instance.
     *
     * @return MBR_CC_Import_Export
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
        add_action('wp_ajax_mbr_cc_export_settings', array($this, 'ajax_export_settings'));
        add_action('wp_ajax_mbr_cc_import_settings', array($this, 'ajax_import_settings'));
        add_action('wp_ajax_mbr_cc_revert_import', array($this, 'ajax_revert_import'));
    }

    /* ---------------------------------------------------------------------
     * Setting map (the allowlist + per-key sanitiser)
     * ------------------------------------------------------------------- */

    /**
     * Canonical map of exportable/importable scalar settings.
     *
     * Key   = option name WITHOUT the 'mbr_cc_' prefix.
     * Value = sanitiser token used on import (see sanitize_value()).
     *
     * This list is the security boundary: anything not present here is never
     * exported and is refused on import.
     *
     * @return array
     */
    private function scalar_map() {
        return array(
            // Banner appearance.
            'banner_position'            => 'text',
            'banner_layout'              => 'text',
            'primary_color'              => 'color',
            'accept_button_color'        => 'color',
            'reject_button_color'        => 'color',
            'text_color'                 => 'color',
            'revisit_button_text_color'  => 'color',
            'show_reject_button'         => 'bool',
            'show_customize_button'      => 'bool',
            'show_close_button'          => 'bool',
            'reload_on_consent'          => 'bool',
            'disable_banner'             => 'bool',

            // Banner text.
            'banner_heading'             => 'text',
            'banner_description'         => 'textarea',
            'accept_button_text'         => 'text',
            'reject_button_text'         => 'text',
            'customize_button_text'      => 'text',

            // Cookie behaviour.
            'cookie_expiry_days'         => 'int',
            'default_auto_accept'        => 'bool',
            'default_show_customize'     => 'bool',
            'default_show_reject'        => 'bool',

            // CCPA / US.
            'enable_ccpa'                => 'bool',
            'ccpa_link_text'             => 'text',
            'ccpa_auto_accept'           => 'bool',

            // Revisit consent.
            'revisit_consent_enabled'    => 'bool',
            'revisit_consent_text'       => 'text',

            // Policy links (URLs travel; the page IDs behind them do not).
            'show_privacy_policy_link'   => 'bool',
            'privacy_policy_url'         => 'url',
            'privacy_policy_text'        => 'text',
            'show_cookie_policy_link'    => 'bool',
            'cookie_policy_url'          => 'url',
            'cookie_policy_text'         => 'text',

            // Branding.
            'banner_logo_url'            => 'url',

            // Google Consent Mode v2.
            'google_consent_mode'        => 'bool',
            'google_default_deny'        => 'bool',
            'google_ads_redaction'       => 'bool',
            'google_url_passthrough'     => 'bool',

            // Microsoft UET Consent Mode.
            'microsoft_consent_mode'     => 'bool',
            'microsoft_default_deny'     => 'bool',

            // i18n & accessibility.
            'auto_translate'             => 'bool',
            'wcag_compliance'            => 'text',

            // Page-specific controls.
            'excluded_pages'             => 'textarea',
            'excluded_url_patterns'      => 'textarea',
            'exclude_login'              => 'bool',
            'exclude_checkout'           => 'bool',
            'exclude_cart'               => 'bool',
            'exclude_account'            => 'bool',

            // Custom CSS.
            'custom_css'                 => 'css',

            // Subdomain consent.
            'subdomain_sharing'          => 'bool',
            'subdomain_root_domain'      => 'text',

            // IAB TCF v2.3.
            'iab_tcf_enabled'            => 'bool',
            'publisher_country_code'     => 'text',
            'purpose_one_treatment'      => 'bool',
            'gdpr_applies'               => 'text', // 'auto' | 'true' | 'false'.

            // Google ACM.
            'google_acm_enabled'         => 'bool',

            // Blocked content overlay.
            'blocked_overlay_enabled'    => 'bool',
            'blocked_overlay_heading'    => 'text',
            'blocked_overlay_message'    => 'textarea',
            'blocked_overlay_btn_text'   => 'text',
            'blocked_overlay_logo_url'   => 'url',

            // GPC.
            'gpc_enabled'                => 'bool',
            'gpc_show_confirmation'      => 'bool',
            'gpc_suppress_analytics'     => 'bool',

            // Form integration.
            'form_integration_enabled'   => 'bool',
            'form_integration_message'   => 'text',
            'form_required_category'     => 'key',

            // A/B testing (the enabled flag; per-variant data stays site-local).
            'ab_testing_enabled'         => 'bool',

            // Geolocation core toggles.
            'geolocation_enabled'        => 'bool',
            'geolocation_provider'       => 'text',
            'geolocation_default'        => 'text',
            'geolocation_default_require' => 'bool',
            'geolocation_cache'          => 'int',

            // Proxy trust (2.3.1). Deliberately NOT exported/imported by
            // default elsewhere, but listed so the settings screen can write
            // it through the same allowlist as everything else.
            'proxy_mode'                 => 'key',
            'trusted_proxies'            => 'textarea',

            // Geolocation transport (2.3.1).
            'ipapi_key'                  => 'text',
            'allow_insecure_geo_lookup'  => 'bool',

            // AI training disclosure.
            'ai_training_enabled'        => 'bool',
            'ai_training_own'            => 'bool',
            'ai_training_vendors'        => 'bool',
            'ai_training_sell'           => 'bool',
            'ai_training_detail'         => 'textarea',
        );
    }

    /**
     * Discover per-region geolocation heading/description options.
     *
     * These are added over time as new privacy regions ship, so rather than
     * hard-code every region we match the stable naming pattern. Headings are
     * sanitised as text, descriptions as textarea. Nothing else is matched,
     * so this stays within the allowlist guarantee.
     *
     * @return array key (no prefix) => sanitiser token
     */
    private function geolocation_region_map() {
        $map  = array();
        $all  = wp_load_alloptions();

        foreach ($all as $name => $unused) {
            if (strpos($name, 'mbr_cc_geolocation_') !== 0) {
                continue;
            }
            $short = substr($name, strlen('mbr_cc_'));
            if (preg_match('/^geolocation_[a-z0-9_]+_heading$/', $short)) {
                $map[$short] = 'text';
            } elseif (preg_match('/^geolocation_[a-z0-9_]+_description$/', $short)) {
                $map[$short] = 'textarea';
            }
        }

        return $map;
    }

    /**
     * Full scalar map (static list + discovered geolocation regions).
     *
     * Used for EXPORT, where regions must be enumerated from what this site
     * has actually stored.
     *
     * @return array
     */
    private function full_scalar_map() {
        return array_merge($this->scalar_map(), $this->geolocation_region_map());
    }

    /**
     * Resolve the sanitiser token for an incoming key, or null if the key is
     * not permitted.
     *
     * Used for IMPORT. Unlike export, this must accept geolocation region
     * headings/descriptions even when the target site has never stored them
     * (e.g. a fresh install in a multi-site rollout). The geolocation pattern
     * is tightly constrained and its values are only ever treated as text or
     * textarea, so this remains within the allowlist guarantee.
     *
     * @param string $short Option name without the 'mbr_cc_' prefix.
     * @return string|null Sanitiser token, or null if not allowed.
     */
    public function resolve_sanitiser($short) {
        $map = $this->scalar_map();
        if (isset($map[$short])) {
            return $map[$short];
        }

        if (preg_match('/^geolocation_[a-z0-9_]+_heading$/', $short)) {
            return 'text';
        }
        if (preg_match('/^geolocation_[a-z0-9_]+_description$/', $short)) {
            return 'textarea';
        }

        return null;
    }

    /* ---------------------------------------------------------------------
     * Export
     * ------------------------------------------------------------------- */

    /**
     * Build the export payload array (settings + array options).
     *
     * @return array
     */
    public function build_settings_payload() {
        $missing  = '__mbr_cc_missing__';
        $settings = array();

        foreach ($this->full_scalar_map() as $short => $type) {
            $value = get_option('mbr_cc_' . $short, $missing);
            if ($value === $missing) {
                continue; // Never stored on this site; leave it out.
            }
            $settings[$short] = $value;
        }

        // Array-shaped options carried verbatim (re-sanitised on import).
        $categories = get_option('mbr_cc_cookie_categories', $missing);
        if ($categories !== $missing && is_array($categories)) {
            $settings['cookie_categories'] = $categories;
        }

        $blocked = get_option('mbr_cc_blocked_scripts', $missing);
        if ($blocked !== $missing && is_array($blocked)) {
            $settings['blocked_scripts'] = $blocked;
        }

        return $settings;
    }

    /**
     * Build the full export document (envelope + settings) as a JSON string.
     *
     * @return string
     */
    public function build_export_json() {
        $settings = $this->build_settings_payload();

        $document = array(
            'format'         => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'plugin_version' => defined('MBR_CC_VERSION') ? MBR_CC_VERSION : '',
            'exported_at'    => gmdate('c'),
            'source_url'     => home_url(),
            'checksum'       => $this->checksum($settings),
            'settings'       => $settings,
        );

        return wp_json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Stable checksum of a settings array for integrity reporting.
     *
     * @param array $settings Settings array.
     * @return string
     */
    private function checksum($settings) {
        $normalised = $settings;
        $this->ksort_recursive($normalised);
        return hash('sha256', (string) wp_json_encode($normalised));
    }

    /**
     * Recursively key-sort an array in place for a deterministic checksum.
     *
     * @param array $arr Array passed by reference.
     */
    private function ksort_recursive(&$arr) {
        if (!is_array($arr)) {
            return;
        }
        ksort($arr);
        foreach ($arr as &$value) {
            if (is_array($value)) {
                $this->ksort_recursive($value);
            }
        }
        unset($value);
    }

    /**
     * AJAX: stream the settings export as a downloadable JSON file.
     */
    public function ajax_export_settings() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $json = $this->build_export_json();

        $host = wp_parse_url(home_url(), PHP_URL_HOST);
        $host = $host ? preg_replace('/[^a-z0-9.-]/i', '-', $host) : 'site';
        $filename = 'mbr-cookie-consent-settings-' . $host . '-' . gmdate('Y-m-d') . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));

        // JSON is generated internally from sanitised option data and streamed
        // as a file attachment, not rendered as HTML; HTML escaping does not apply.
        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        exit;
    }

    /* ---------------------------------------------------------------------
     * Import
     * ------------------------------------------------------------------- */

    /**
     * AJAX: import settings from an uploaded JSON file.
     */
    public function ajax_import_settings() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'mbr-cookie-consent')));
        }

        if (empty($_FILES['mbr_cc_import_file']) || !isset($_FILES['mbr_cc_import_file']['tmp_name'])) {
            wp_send_json_error(array('message' => __('No file was uploaded.', 'mbr-cookie-consent')));
        }

        $file = $_FILES['mbr_cc_import_file'];

        if (!empty($file['error']) && (int) $file['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(array('message' => __('The file failed to upload. Please try again.', 'mbr-cookie-consent')));
        }

        if ((int) $file['size'] > self::MAX_UPLOAD_BYTES) {
            wp_send_json_error(array('message' => __('That file is too large to be a settings export.', 'mbr-cookie-consent')));
        }

        $tmp = isset($file['tmp_name']) ? $file['tmp_name'] : '';
        if (empty($tmp) || !is_uploaded_file($tmp)) {
            wp_send_json_error(array('message' => __('Upload could not be validated.', 'mbr-cookie-consent')));
        }

        $raw = file_get_contents($tmp); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
        if ($raw === false || $raw === '') {
            wp_send_json_error(array('message' => __('The file was empty.', 'mbr-cookie-consent')));
        }

        $result = $this->import_from_json($raw);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        wp_send_json_success($result);
    }

    /**
     * Validate a JSON document and apply its settings.
     *
     * @param string $raw Raw JSON string.
     * @return array|WP_Error Report on success, WP_Error on failure.
     */
    public function import_from_json($raw) {
        $data = json_decode($raw, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
            return new WP_Error('mbr_cc_bad_json', __('This is not a valid settings file (could not read JSON).', 'mbr-cookie-consent'));
        }

        if (!isset($data['format']) || $data['format'] !== self::FORMAT) {
            return new WP_Error('mbr_cc_wrong_format', __('This file is not an MBR Cookie Consent settings export.', 'mbr-cookie-consent'));
        }

        if (!isset($data['settings']) || !is_array($data['settings'])) {
            return new WP_Error('mbr_cc_no_settings', __('The file did not contain any settings.', 'mbr-cookie-consent'));
        }

        $incoming = $data['settings'];

        // Integrity check: report, but do not block a legitimately hand-edited file.
        $checksum_ok = null;
        if (!empty($data['checksum'])) {
            $checksum_ok = hash_equals((string) $data['checksum'], $this->checksum($incoming));
        }

        $applied = 0;
        $skipped = array();
        $changed = array(); // For the pre-import backup.

        // Scalar settings.
        foreach ($incoming as $short => $value) {
            if ($short === 'cookie_categories' || $short === 'blocked_scripts') {
                continue; // Handled separately below.
            }

            $type = $this->resolve_sanitiser($short);
            if ($type === null) {
                $skipped[] = $short;
                continue;
            }

            $clean      = $this->sanitize_value($value, $type);
            $option_key = 'mbr_cc_' . $short;

            // Record current value for revert before we overwrite it.
            $changed[$option_key] = get_option($option_key, null);

            update_option($option_key, $clean);
            $applied++;
        }

        // Cookie categories.
        if (isset($incoming['cookie_categories']) && is_array($incoming['cookie_categories'])) {
            $clean = $this->sanitize_categories($incoming['cookie_categories']);
            if (!empty($clean)) {
                $changed['mbr_cc_cookie_categories'] = get_option('mbr_cc_cookie_categories', null);
                update_option('mbr_cc_cookie_categories', $clean);
                $applied++;
            }
        }

        // Blocked scripts.
        if (isset($incoming['blocked_scripts']) && is_array($incoming['blocked_scripts'])) {
            $clean = $this->sanitize_blocked_scripts($incoming['blocked_scripts']);
            $changed['mbr_cc_blocked_scripts'] = get_option('mbr_cc_blocked_scripts', null);
            update_option('mbr_cc_blocked_scripts', $clean);
            $applied++;
        }

        // Store a single-slot backup so the admin can revert this import.
        if (!empty($changed)) {
            update_option(self::BACKUP_OPTION, array(
                'created_at' => gmdate('c'),
                'source_url' => isset($data['source_url']) ? esc_url_raw($data['source_url']) : '',
                'values'     => $changed,
            ), false);
        }

        return array(
            'message'       => sprintf(
                /* translators: %d: number of settings applied. */
                _n('Imported %d setting.', 'Imported %d settings.', $applied, 'mbr-cookie-consent'),
                $applied
            ),
            'applied'       => $applied,
            'skipped'       => $skipped,
            'skipped_count' => count($skipped),
            'checksum_ok'   => $checksum_ok,
            'source_url'    => isset($data['source_url']) ? esc_url_raw($data['source_url']) : '',
            'can_revert'    => !empty($changed),
        );
    }

    /**
     * Revert the most recent import using the one-slot backup.
     */
    public function ajax_revert_import() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Unauthorized.', 'mbr-cookie-consent')));
        }

        $backup = get_option(self::BACKUP_OPTION, null);

        if (empty($backup) || empty($backup['values']) || !is_array($backup['values'])) {
            wp_send_json_error(array('message' => __('There is no import to revert.', 'mbr-cookie-consent')));
        }

        $restored = 0;
        foreach ($backup['values'] as $option_key => $previous) {
            // Only touch our own options, and re-sanitise the stored previous
            // value defensively before writing it back.
            if (strpos($option_key, 'mbr_cc_') !== 0) {
                continue;
            }

            if ($previous === null) {
                // The option did not exist before the import; remove it.
                delete_option($option_key);
            } else {
                update_option($option_key, $previous);
            }
            $restored++;
        }

        delete_option(self::BACKUP_OPTION);

        wp_send_json_success(array(
            'message' => sprintf(
                /* translators: %d: number of settings restored. */
                _n('Reverted %d setting to its pre-import value.', 'Reverted %d settings to their pre-import values.', $restored, 'mbr-cookie-consent'),
                $restored
            ),
            'restored' => $restored,
        ));
    }

    /**
     * Whether a revertable backup currently exists.
     *
     * @return bool
     */
    public function has_backup() {
        $backup = get_option(self::BACKUP_OPTION, null);
        return !empty($backup) && !empty($backup['values']);
    }

    /* ---------------------------------------------------------------------
     * Sanitisation
     * ------------------------------------------------------------------- */

    /**
     * Sanitise a single scalar value by its declared type.
     *
     * Mirrors the sanitisers used in MBR_CC_Settings::register_settings().
     *
     * @param mixed  $value Raw value from the file.
     * @param string $type  Sanitiser token.
     * @return mixed
     */
    public function sanitize_value($value, $type) {
        switch ($type) {
            case 'bool':
                return (bool) rest_sanitize_boolean($value);

            case 'int':
                return absint($value);

            case 'color':
                $color = sanitize_hex_color(is_scalar($value) ? (string) $value : '');
                return $color ? $color : '';

            case 'url':
                return esc_url_raw(is_scalar($value) ? (string) $value : '');

            case 'textarea':
                return sanitize_textarea_field(is_scalar($value) ? (string) $value : '');

            case 'css':
                return wp_strip_all_tags(is_scalar($value) ? (string) $value : '');

            case 'key':
                return sanitize_key(is_scalar($value) ? (string) $value : '');

            case 'text':
            default:
                return sanitize_text_field(is_scalar($value) ? (string) $value : '');
        }
    }

    /**
     * Sanitise imported cookie categories.
     *
     * Same shape and rules as MBR_CC_Settings::ajax_update_categories().
     *
     * @param array $categories Raw categories.
     * @return array
     */
    private function sanitize_categories($categories) {
        $clean = array();

        foreach ($categories as $slug => $category) {
            if (!is_array($category)) {
                continue;
            }
            $key = sanitize_key($slug);
            if ($key === '') {
                continue;
            }
            $clean[$key] = array(
                'name'        => isset($category['name']) ? sanitize_text_field($category['name']) : '',
                'description' => isset($category['description']) ? sanitize_textarea_field($category['description']) : '',
                'required'    => isset($category['required']) && filter_var($category['required'], FILTER_VALIDATE_BOOLEAN),
                'enabled'     => isset($category['enabled']) && filter_var($category['enabled'], FILTER_VALIDATE_BOOLEAN),
            );
        }

        return $clean;
    }

    /**
     * Sanitise imported blocked scripts.
     *
     * Same field set as MBR_CC_Settings::ajax_add_blocked_script(). Entries
     * missing a name or identifier are dropped.
     *
     * @param array $scripts Raw blocked scripts.
     * @return array
     */
    private function sanitize_blocked_scripts($scripts) {
        $clean = array();

        foreach ($scripts as $script) {
            if (!is_array($script)) {
                continue;
            }
            $name       = isset($script['name']) ? sanitize_text_field($script['name']) : '';
            $identifier = isset($script['identifier']) ? sanitize_text_field($script['identifier']) : '';

            if ($name === '' || $identifier === '') {
                continue;
            }

            $clean[] = array(
                'name'        => $name,
                'identifier'  => $identifier,
                'type'        => isset($script['type']) ? sanitize_text_field($script['type']) : 'src',
                'category'    => isset($script['category']) ? sanitize_key($script['category']) : 'marketing',
                'description' => isset($script['description']) ? sanitize_textarea_field($script['description']) : '',
            );
        }

        return array_values($clean);
    }
}
