<?php
/**
 * Plugin Name: MBR Cookie Consent
 * Plugin URI: https://littlewebshack.com/mbr-cookie-consent/
 * Description: GDPR/EEA, UK DUAA, CCPA/US multi-state, LGPD, PIPEDA, Quebec Law 25, Swiss nFADP, Australia Privacy Act, India DPDP, Vietnam PDPL, Indonesia UU PDP, Nigeria NDPA, China PIPL, South Korea PIPA, Saudi PDPL, South Africa POPIA, and global privacy law compliant cookie consent management with GPC signal support, automatic script blocking, and consent logging.
 * Version: 2.3.3
 * Author: Robert Palmer
 * Author URI: https://littlewebshack.com
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: mbr-cookie-consent
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * Network: true
 *
 * LEGAL DISCLAIMER: This plugin provides technical tools to help implement cookie consent
 * mechanisms. It does not constitute legal advice. Users are responsible for ensuring
 * their use of this plugin complies with applicable laws and should consult with legal
 * counsel regarding their specific compliance requirements.
 *
 */


if (!defined('ABSPATH')) {
    exit;
}

// Buy Me a Coffee
add_filter( 'plugin_row_meta', function ( $links, $file, $data ) {
    if ( ! function_exists( 'plugin_basename' ) || $file !== plugin_basename( __FILE__ ) ) {
        return $links;
    }

    $url = 'https://buymeacoffee.com/robertpalmer/';
    $links[] = sprintf(
	// translators: %s: The name of the plugin author.
        '<a href="%s" target="_blank" rel="noopener nofollow" aria-label="%s">☕ %s</a>',
        esc_url( $url ),
		// translators: %s: The name of the plugin author.
        esc_attr( sprintf( __( 'Buy %s a coffee', 'mbr-cookie-consent' ), isset( $data['AuthorName'] ) ? $data['AuthorName'] : __( 'the author', 'mbr-cookie-consent' ) ) ),
        esc_html__( 'Buy me a coffee', 'mbr-cookie-consent' )
    );

    return $links;
}, 10, 3 );

// Define plugin constants.
define('MBR_CC_VERSION', '2.3.3');
define('MBR_CC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('MBR_CC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('MBR_CC_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Self-hosted update delivery via Plugin Update Checker (PUC).
 *
 * Checks littlewebshack.com for new releases so updates appear in the standard
 * WordPress "Plugins" screen. No telemetry is sent; PUC only requests the
 * metadata JSON below and, when an update is available, the packaged ZIP.
 */
require_once MBR_CC_PLUGIN_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

$mbr_cc_update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://raw.githubusercontent.com/HarbourBob/mbr-updates/main/mbr-cookie-consent.json',
    __FILE__,
    'mbr-cookie-consent'
);

/**
 * Main plugin class.
 */
class MBR_Cookie_Consent {
    
    /**
     * Single instance of the class.
     *
     * @var MBR_Cookie_Consent
     */
    private static $instance = null;
    
    /**
     * Get single instance.
     *
     * @return MBR_Cookie_Consent
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
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    /**
     * Load required files.
     */
    private function load_dependencies() {
        // Shared client-IP resolution. Must load before the database and
        // geolocation classes, both of which depend on it.
        require_once MBR_CC_PLUGIN_DIR . 'includes/mbr-cc-ip.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-cache.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-translations.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-database.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-geolocation.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-region-config.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-gpc-handler.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-script-blocker.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-blocked-placeholder.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-banner.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-consent-manager.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-consent-modes.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-i18n-accessibility.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-enhanced-customization.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-subdomain-consent.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-google-acm.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-cookie-scanner.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-policy-generator.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-privacy-policy-generator.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-form-integration.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-ab-testing.php';
        require_once MBR_CC_PLUGIN_DIR . 'includes/class-mbr-cc-import-export.php';
        require_once MBR_CC_PLUGIN_DIR . 'admin/class-mbr-cc-admin.php';
        require_once MBR_CC_PLUGIN_DIR . 'admin/class-mbr-cc-settings.php';
        require_once MBR_CC_PLUGIN_DIR . 'admin/geolocation-ajax.php';
        
        // Load network admin for multisite
        if (is_multisite()) {
            require_once MBR_CC_PLUGIN_DIR . 'admin/class-mbr-cc-network-admin.php';
        }
    }
    
    /**
     * Initialize WordPress hooks.
     */
    private function init_hooks() {
        // Multisite-aware activation
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        
        // Handle new site creation in multisite
        add_action('wpmu_new_blog', array($this, 'activate_new_site'), 10, 1);
        
        // Handle site deletion in multisite
        add_action('delete_blog', array($this, 'delete_site_data'), 10, 1);
        
        add_action('plugins_loaded', array($this, 'init'));
        add_action('init', array($this, 'load_textdomain'));
    }
    
    /**
     * Initialize plugin components.
     */
    public function init() {
        // Run any pending version upgrades before anything reads options.
        // This must happen here rather than in the activation hook, because
        // WordPress does NOT fire register_activation_hook() on plugin update.
        $this->maybe_upgrade();
        
        // Initialize database handler.
        MBR_CC_Database::get_instance();
        
        // Watch for settings changes so page caches are purged on save.
        MBR_CC_Cache::init();
        MBR_CC_Translations::get_instance();
        
        // Initialize script blocker (must run early).
        MBR_CC_Script_Blocker::get_instance();
        
        // Initialize consent manager.
        MBR_CC_Consent_Manager::get_instance();
        
        // Initialize consent modes (Google & Microsoft).
        MBR_CC_Consent_Modes::get_instance();
        
        // Initialize i18n and accessibility.
        MBR_CC_I18n_Accessibility::get_instance();
        
        // Initialize enhanced customization.
        MBR_CC_Enhanced_Customization::get_instance();
        
        // Initialize subdomain consent sharing.
        MBR_CC_Subdomain_Consent::get_instance();
        
        
        // Initialize Google ACM.
        MBR_CC_Google_ACM::get_instance();
        
        // Initialize geolocation and regional config (must run before banner).
        MBR_CC_Geolocation::get_instance();
        MBR_CC_Region_Config::get_instance();
        
        // Initialize GPC (Global Privacy Control) handler (must run before banner).
        MBR_CC_GPC_Handler::get_instance();
        
        // Initialize banner display.
        MBR_CC_Banner::get_instance();
        
        // Initialize network admin (multisite only)
        if (is_multisite() && is_network_admin()) {
            MBR_CC_Network_Admin::get_instance();
        }
        
        // Initialize admin interface.
        if (is_admin()) {
            MBR_CC_Admin::get_instance();
            MBR_CC_Settings::get_instance();
            MBR_CC_Import_Export::get_instance();
        }
        
        // Initialize cookie scanner.
        MBR_CC_Cookie_Scanner::get_instance();
        
        // Initialize policy generator.
        MBR_CC_Policy_Generator::get_instance();
        
        // Initialize privacy policy generator.
        MBR_CC_Privacy_Policy_Generator::get_instance();
        MBR_CC_Form_Integration::get_instance();
        MBR_CC_AB_Testing::get_instance();
    }
    
    /**
     * Load plugin textdomain for translations.
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'mbr-cookie-consent',
            false,
            dirname(MBR_CC_PLUGIN_BASENAME) . '/languages'
        );
    }
    
    /**
     * Plugin activation.
     */
    public function activate($network_wide = false) {
        global $wpdb;
        
        if (is_multisite() && $network_wide) {
            // Network activation - activate for all sites
            $blog_ids = $wpdb->get_col("SELECT blog_id FROM $wpdb->blogs");
            
            foreach ($blog_ids as $blog_id) {
                switch_to_blog($blog_id);
                $this->activate_single_site();
                restore_current_blog();
            }
            
            // Set network-wide default options
            $this->set_network_default_options();
        } else {
            // Single site activation
            $this->activate_single_site();
        }
    }
    
    /**
     * Activate plugin for a single site.
     */
    private function activate_single_site() {
        // Create database tables (network-wide tables)
        MBR_CC_Database::create_tables();
        
        // Run version-gated migrations for existing installations FIRST, while
        // the stored version marker still reflects the old release. On a fresh
        // install these are no-ops.
        $this->maybe_upgrade();
        
        // Set default options for this site
        $this->set_default_options();
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Activate plugin for newly created site in network.
     *
     * @param int $blog_id Blog ID of the new site.
     */
    public function activate_new_site($blog_id) {
        if (is_plugin_active_for_network(MBR_CC_PLUGIN_BASENAME)) {
            switch_to_blog($blog_id);
            $this->activate_single_site();
            restore_current_blog();
        }
    }
    
    /**
     * Delete site data when a site is deleted from network.
     *
     * @param int $blog_id Blog ID being deleted.
     */
    public function delete_site_data($blog_id) {
        global $wpdb;
        
        // Delete consent logs for this site
        $table_name = $wpdb->base_prefix . 'mbr_cc_consent_logs';
        $wpdb->delete($table_name, array('blog_id' => $blog_id), array('%d'));
        
        // Note: We don't delete the tables themselves as they're network-wide
    }
    
    /**
     * Run version-gated upgrade routines.
     *
     * Fires on every load via init(), but does nothing once the stored version
     * matches the running version. Kept deliberately cheap: a single option
     * read in the common case.
     *
     * Why not the activation hook? Because register_activation_hook() does not
     * fire when a plugin is updated in place (which is how every existing site
     * receives this release via PUC). Migrations that must run on upgrade have
     * to be triggered from a normal request.
     *
     * Multisite note: this runs per-site, and get_option()/update_option() are
     * per-site, so each site in a network migrates independently on first load.
     */
    private function maybe_upgrade() {
        $stored_version = get_option('mbr_cc_version', '');
        
        if ($stored_version === MBR_CC_VERSION) {
            return;
        }
        
        // 1.0.3 — revisit button text colour correction. Retained here so a
        // very old site that never reactivated still gets the fix.
        if (version_compare($stored_version, '1.0.3', '<')) {
            if (get_option('mbr_cc_revisit_button_text_color', '#ffffff') === '#ffffff') {
                update_option('mbr_cc_revisit_button_text_color', '#000000');
            }
        }
        
        // 2.3.0 — default (Rest of World) region became opt-in.
        if (version_compare($stored_version, '2.3.0', '<')) {
            $this->upgrade_to_230($stored_version);
        }
        
        // 2.3.1 — geolocation lookups moved off plaintext HTTP.
        if ($stored_version !== '' && version_compare($stored_version, '2.3.1', '<')) {
            $this->upgrade_to_231();
        }
        
        // 2.3.2 — plugin settings now win over WPML/Polylang in the default language.
        if ($stored_version !== '' && version_compare($stored_version, '2.3.2', '<')) {
            $this->upgrade_to_232();
        }
        
        // 2.3.3 — unwind slashes accumulated by the unslashed AJAX save.
        if ($stored_version !== '' && version_compare($stored_version, '2.3.3', '<')) {
            $this->upgrade_to_233();
        }
        
        update_option('mbr_cc_version', MBR_CC_VERSION);
    }
    
    /**
     * Upgrade routine for 2.3.0.
     *
     * 2.3.0 flipped the shipped defaults for the "Rest of World" region from
     * implied consent to opt-in. Those defaults live in the third argument of
     * get_option() inside MBR_CC_Region_Config::get_default_config(), which
     * means they only apply where no option row exists — and no option row has
     * ever existed for them, because they were never written by
     * set_default_options().
     *
     * So changing the code default would silently change banner behaviour on
     * every existing site that had never touched those settings. A point
     * release should not do that.
     *
     * This routine therefore pins the OLD behaviour explicitly for any site
     * upgrading from below 2.3.0, writing the previous values as real option
     * rows. New installations get the option rows written with the new strict
     * values by set_default_options() instead. Either way the behaviour is
     * explicit rather than inherited from a code default that can move again.
     *
     * Site owners can still change any of these afterwards; this only decides
     * the starting position.
     *
     * @param string $stored_version Version the site is upgrading from.
     */
    private function upgrade_to_230($stored_version) {
        // Nothing to preserve on a site that has never stored settings — that
        // is a fresh install, and set_default_options() will write the new
        // strict values instead. mbr_cc_banner_heading is the marker: it has
        // been written on activation since the earliest releases.
        if (false === get_option('mbr_cc_banner_heading')) {
            return;
        }
        
        $legacy_defaults = array(
            'mbr_cc_geolocation_default_require' => false,
            'mbr_cc_default_auto_accept'         => true,
            'mbr_cc_default_show_reject'         => false,
            'mbr_cc_default_show_customize'      => true,
        );
        
        // add_option() is a no-op when the row already exists, so this both
        // pins the old behaviour on upgrading sites and leaves a fresh
        // install's strict values untouched. It is also race-safe.
        foreach ($legacy_defaults as $option_key => $legacy_value) {
            add_option($option_key, $legacy_value);
        }
        
        // Flag it so the admin can surface a one-time notice explaining that
        // the shipped default changed but this site was left as it was.
        if ($stored_version !== '') {
            update_option('mbr_cc_230_default_region_preserved', true);
        }
    }
    
    /**
     * Upgrade routine for 2.3.1.
     *
     * The ip-api.com provider was queried over plain HTTP, which means an
     * on-path attacker could rewrite the country code and choose which privacy
     * regime a visitor was served — and visitor IP addresses travelled to a
     * third party in cleartext, which is difficult to defend under GDPR Art. 44
     * in a plugin whose whole job is privacy compliance.
     *
     * ip-api.com only offers HTTPS on its paid tier, so sites still using it
     * without a key are moved to ipapi.co, which is free over HTTPS. Anyone who
     * prefers ip-api's higher free rate limit can opt back in explicitly on the
     * geolocation settings screen, or add a pro key.
     *
     * Only sites with geolocation actually switched on are touched, and a flag
     * is left behind so the change can be explained in a notice rather than
     * happening silently.
     */
    private function upgrade_to_231() {
        if (!get_option('mbr_cc_geolocation_enabled', false)) {
            return;
        }
        
        $provider = get_option('mbr_cc_geolocation_provider', 'ip-api');
        $api_key  = trim((string) get_option('mbr_cc_ipapi_key', ''));
        
        if ($provider !== 'ip-api' || $api_key !== '') {
            return;
        }
        
        update_option('mbr_cc_geolocation_provider', 'ipapi');
        update_option('mbr_cc_231_geo_provider_switched', true);
    }
    
    /**
     * Upgrade routine for 2.3.2.
     *
     * Banner text set in this plugin now takes precedence over WPML and
     * Polylang in the site's default language. That fixes a real defect —
     * icl_t() returned whatever was registered when a string was first seen and
     * ignored the updated value, so editing the wording here appeared to do
     * nothing — but it is a behaviour change, and anyone who worked around the
     * old behaviour by editing the default-language string in WPML's String
     * Translation screen will now see this plugin's setting win instead.
     *
     * Only flagged on sites actually running a multilingual plugin, so the
     * notice never appears where it would mean nothing.
     */
    private function upgrade_to_232() {
        $multilingual = defined('ICL_SITEPRESS_VERSION')
            || function_exists('icl_t')
            || function_exists('pll__');
        
        if ($multilingual) {
            update_option('mbr_cc_232_multilingual_precedence', true);
        }
    }
    
    /**
     * Upgrade routine for 2.3.3.
     *
     * The AJAX settings handler wrote $_POST without wp_unslash(), so the
     * slashing WordPress applies in wp_magic_quotes() was stored verbatim. The
     * stored value was then rendered back into the field by esc_textarea(), and
     * slashed again on the next save — so the run of backslashes in front of
     * every quote followed 2n + 1: " then \" then \\\" then seven, then fifteen.
     *
     * The handler is fixed, but stored values still carry the damage. Text and
     * textarea options are unwound here.
     *
     * mbr_cc_custom_css is deliberately excluded. CSS uses backslashes as real
     * escapes — content: "\201C" and the like — and there is no reliable way to
     * tell an escape the user typed from one this bug manufactured. Anyone
     * affected there has to correct it by hand, which is why the changelog says
     * so plainly.
     */
    private function upgrade_to_233() {
        global $wpdb;
        
        if (!class_exists('MBR_CC_Import_Export')) {
            return;
        }
        
        $importer = MBR_CC_Import_Export::get_instance();
        
        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('mbr_cc_') . '%'
            )
        );
        
        $repaired = 0;
        
        foreach ((array) $names as $name) {
            if ('mbr_cc_custom_css' === $name) {
                continue;
            }
            
            // resolve_sanitiser() already knows every settable key and its
            // type, including the geolocation regional headings and
            // descriptions it matches by pattern. Reusing it means this cannot
            // drift out of step with what the save handler accepts.
            $type = $importer->resolve_sanitiser(substr($name, 7));
            
            if ('text' !== $type && 'textarea' !== $type) {
                continue;
            }
            
            $value = get_option($name);
            
            if (!is_string($value) || false === strpos($value, '\\')) {
                continue;
            }
            
            $fixed = self::strip_accumulated_slashes($value);
            
            if ($fixed !== $value) {
                update_option($name, $fixed);
                $repaired++;
            }
        }
        
        if ($repaired > 0) {
            MBR_CC_Cache::flush('slash_repair');
        }

        // IAB TCF has been withdrawn — it never worked. Anyone who had it
        // switched on was feeding vendors a fixed, meaningless consent string
        // and, if they had generated a privacy policy, publishing a claim of
        // TCF participation that was not true. Retire the option and tell them.
        if (get_option('mbr_cc_iab_tcf_enabled')) {
            update_option('mbr_cc_233_tcf_withdrawn', true);
        }

        delete_option('mbr_cc_iab_tcf_enabled');
    }
    
    /**
     * Collapse backslash runs produced by repeated re-slashing.
     *
     * @param string $value Stored value.
     * @return string Corrected value.
     */
    private static function strip_accumulated_slashes($value) {
        // A quote re-slashed n times arrives behind a run of backslashes, none
        // of which were typed. Drop the run and keep the quote.
        $value = preg_replace('/\\\\+(["\'])/', '$1', $value);
        
        // Backslashes elsewhere in the string doubled on each save too, so a
        // run of two or more collapses back to the single character. A lone
        // backslash is left alone: it is far more likely to be deliberate than
        // to be damage.
        return preg_replace('/\\\\{2,}/', '\\\\', $value);
    }
    
    /**
     * Plugin deactivation.
     */
    public function deactivate() {
        // Flush rewrite rules.
        flush_rewrite_rules();
    }
    
    /**
     * Set default plugin options.
     */
    private function set_default_options() {
        // Detect a genuinely fresh install BEFORE any option is written.
        // This decides the starting posture for the "Rest of World" region:
        // new sites get the 2.3.0 opt-in defaults, existing sites keep the
        // lenient pre-2.3.0 behaviour. Checking here also closes the
        // deactivate-then-reactivate path, which runs this method but not
        // the update flow that maybe_upgrade() covers.
        $is_fresh_install = (false === get_option('mbr_cc_banner_heading'));
        
        $defaults = array(
            'banner_position' => 'bottom',
            'banner_layout' => 'bar',
            'primary_color' => '#0073aa',
            'accept_button_color' => '#00a32a',
            'reject_button_color' => '#d63638',
            'text_color' => '#ffffff',
            'revisit_button_text_color' => '#000000',
            'banner_glassmorphism' => false,
            'glass_opacity' => 82,
            'glass_blur' => 14,
            'banner_dark_mode' => 'off',
            'banner_logo_id' => 0,
            'show_reject_button' => true,
            'show_customize_button' => true,
            'show_close_button' => false,
            'reload_on_consent' => false,
            'cookie_expiry_days' => 365,
            'enable_ccpa' => false,
            'ccpa_link_text' => 'Do Not Sell or Share My Personal Information',
            'banner_heading' => 'We value your privacy',
            'banner_description' => 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.',
            'accept_button_text' => 'Accept All',
            'reject_button_text' => 'Reject All',
            'customize_button_text' => 'Customize',
            'revisit_consent_enabled' => true,
            'revisit_consent_text' => 'Cookie Settings',
            'show_privacy_policy_link' => false,
            'privacy_policy_url' => '',
            'privacy_policy_text' => 'Privacy Policy',
            'show_cookie_policy_link' => false,
            'cookie_policy_url' => '',
            'cookie_policy_text' => 'Cookie Policy',
            'banner_logo_url' => '',
            'google_consent_mode' => false,
            'google_default_deny' => true,
            'google_ads_redaction' => true,
            'google_url_passthrough' => false,
            'microsoft_consent_mode' => false,
            'microsoft_default_deny' => true,
            'auto_translate' => true,
            'wcag_compliance' => true,
            'excluded_pages' => array(),
            'excluded_url_patterns' => '',
            'exclude_login' => false,
            'exclude_checkout' => false,
            'exclude_cart' => false,
            'exclude_account' => false,
            'custom_css' => '',
            'subdomain_sharing' => false,
            'subdomain_root_domain' => '',
            'publisher_country_code' => '',
            'purpose_one_treatment' => false,
            'gdpr_applies' => 'auto',
            'google_acm_enabled' => false,
            // Blocked content overlay (v1.7.0).
            'blocked_overlay_enabled'  => false,
            'blocked_overlay_heading'  => '',
            'blocked_overlay_message'  => '',
            'blocked_overlay_btn_text' => '',
            'blocked_overlay_logo_url' => '',
        );
        
        foreach ($defaults as $key => $value) {
            if (false === get_option('mbr_cc_' . $key)) {
                add_option('mbr_cc_' . $key, $value);
            }
        }
        
        // "Rest of World" region posture. Written as explicit option rows so
        // behaviour never silently shifts when a shipped code default changes.
        // Fresh installs get the 2.3.0 opt-in posture; anything else keeps the
        // pre-2.3.0 lenient values. See MBR_CC_Region_Config::get_default_config().
        $region_defaults = $is_fresh_install
            ? array(
                'mbr_cc_geolocation_default_require' => true,
                'mbr_cc_default_auto_accept'         => false,
                'mbr_cc_default_show_reject'         => true,
                'mbr_cc_default_show_customize'      => true,
            )
            : array(
                'mbr_cc_geolocation_default_require' => false,
                'mbr_cc_default_auto_accept'         => true,
                'mbr_cc_default_show_reject'         => false,
                'mbr_cc_default_show_customize'      => true,
            );
        
        foreach ($region_defaults as $option_key => $option_value) {
            add_option($option_key, $option_value);
        }
        
        // AI / LLM training disclosure (Connecticut SB 1295). Off by default —
        // the site owner must actively state their position.
        add_option('mbr_cc_ai_training_enabled', false);
        add_option('mbr_cc_ai_training_own', false);
        add_option('mbr_cc_ai_training_vendors', false);
        add_option('mbr_cc_ai_training_sell', false);
        add_option('mbr_cc_ai_training_detail', '');
        
        // Record the version so maybe_upgrade() knows this site is current.
        update_option('mbr_cc_version', MBR_CC_VERSION);
        
        // Create default cookie categories if they don't exist.
        $this->create_default_categories();
    }
    
    /**
     * Set network-wide default options (multisite).
     */
    private function set_network_default_options() {
        // Enable multisite mode
        if (false === get_site_option('mbr_cc_multisite_enabled')) {
            add_site_option('mbr_cc_multisite_enabled', true);
        }
        
        // Network-wide settings (can be overridden per-site)
        $network_defaults = array(
            'network_banner_position' => 'bottom',
            'network_banner_layout' => 'bar',
            'network_primary_color' => '#0073aa',
            'network_accept_button_color' => '#00a32a',
            'network_reject_button_color' => '#d63638',
            'network_text_color' => '#ffffff',
            'network_show_reject_button' => true,
            'network_show_customize_button' => true,
            'network_show_close_button' => false,
            'network_cookie_expiry_days' => 365,
            'network_enable_ccpa' => false,
            'network_banner_heading' => 'We value your privacy',
            'network_banner_description' => 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.',
        );
        
        foreach ($network_defaults as $key => $value) {
            if (false === get_site_option('mbr_cc_' . $key)) {
                add_site_option('mbr_cc_' . $key, $value);
            }
        }
    }
    
    /**
     * Create default cookie categories.
     */
    private function create_default_categories() {
        $categories = get_option('mbr_cc_cookie_categories', array());
        
        if (empty($categories)) {
            $categories = array(
                'necessary' => array(
                    'name' => 'Necessary',
                    'description' => 'Necessary cookies are essential for the website to function properly. These cookies ensure basic functionalities and security features of the website.',
                    'required' => true,
                    'enabled' => true,
                ),
                'analytics' => array(
                    'name' => 'Analytics',
                    'description' => 'Analytics cookies help us understand how visitors interact with our website by collecting and reporting information anonymously.',
                    'required' => false,
                    'enabled' => false,
                ),
                'marketing' => array(
                    'name' => 'Marketing',
                    'description' => 'Marketing cookies are used to track visitors across websites to display relevant advertisements and encourage user engagement.',
                    'required' => false,
                    'enabled' => false,
                ),
                'preferences' => array(
                    'name' => 'Preferences',
                    'description' => 'Preference cookies enable a website to remember information that changes the way the website behaves or looks.',
                    'required' => false,
                    'enabled' => false,
                ),
            );
            
            update_option('mbr_cc_cookie_categories', $categories);
        }
    }
}

/**
 * Initialize the plugin.
 */
function mbr_cookie_consent() {
    return MBR_Cookie_Consent::get_instance();
}

// Start the plugin.
mbr_cookie_consent();
