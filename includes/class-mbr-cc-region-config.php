<?php
/**
 * Region-Specific Banner Configuration
 * Adjusts banner behavior based on detected region
 *
 * @package MBR_Cookie_Consent
 * @version 2.0.0
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_Region_Config {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * Geolocation instance
     */
    private $geo;
    
    /**
     * Get singleton instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Constructor
     */
    private function __construct() {
        $this->geo = mbr_cc_geolocation();
        
        // Filter banner configuration based on region
        add_filter('mbr_cc_banner_config', array($this, 'apply_region_config'));
    }
    
    /**
     * Apply region-specific configuration
     */
    public function apply_region_config($config) {
        
        // Check constant first, then database option
        $geo_enabled = defined('MBR_CC_FORCE_GEOLOCATION') && MBR_CC_FORCE_GEOLOCATION;
        
        if (!$geo_enabled) {
            $geo_enabled = get_option('mbr_cc_geolocation_enabled', false);
        }
        
        if (!$geo_enabled) {
            return $config;
        }
        
        $region = $this->geo->get_region();
        
        // Map legacy region keys to new method names.
        $legacy_map = array(
            'eu_uk' => 'eu_gdpr',
            'ccpa'  => 'us_multi',
        );
        if (isset($legacy_map[$region])) {
            $region = $legacy_map[$region];
        }
        
        // Get region-specific overrides
        $method = "get_{$region}_config";
        if (method_exists($this, $method)) {
            $region_config = $this->$method();
            $config = array_merge($config, $region_config);
        }
        
        
        return $config;
    }
    
    /**
     * EU (GDPR / ePrivacy Directive) Configuration
     * Strict opt-in — no change from the original ePrivacy rules.
     */
    private function get_eu_gdpr_config() {
        return array(
            // GDPR + ePrivacy requires explicit consent for all non-essential cookies
            'require_consent' => true,
            
            // Reject button must be equally prominent
            'show_reject_button' => true,
            'reject_button_prominence' => 'equal',
            
            // Show customize/preferences button
            'show_customize_button' => true,
            
            // Don't auto-accept on scroll/click
            'auto_accept_on_scroll' => false,
            'auto_accept_on_click' => false,
            
            // Show all cookie categories
            'show_categories' => true,
            
            // EU-specific text (falls back to legacy eu_uk option keys)
            'banner_heading' => get_option('mbr_cc_geolocation_eu_heading', get_option('mbr_cc_geolocation_eu_uk_heading', 'We value your privacy')),
            'banner_description' => get_option('mbr_cc_geolocation_eu_description', get_option('mbr_cc_geolocation_eu_uk_description',
                'We use cookies to enhance your experience. By clicking "Accept", you consent to our use of cookies. You can manage your preferences or reject non-essential cookies.'
            )),
            
            // No CCPA link for EU
            'enable_ccpa' => false,
        );
    }
    
    /**
     * UK (UK GDPR + Data Use and Access Act 2025) Configuration
     *
     * The DUAA received Royal Assent 19 June 2025 and PECR cookie amendments
     * came into force 5 February 2026. Key differences from EU:
     *
     * - Analytics cookies (sole purpose: aggregate statistics) are EXEMPT from consent.
     * - Functionality/appearance cookies are EXEMPT from consent.
     * - Security and software-update cookies are EXEMPT from consent.
     * - Advertising/marketing cookies STILL require explicit consent.
     * - Transparency + easy opt-out is STILL required for exempt categories.
     * - PECR fines now match UK GDPR levels: up to 17.5M GBP or 4% of turnover.
     *
     * The banner is still shown for transparency and to collect advertising consent,
     * but analytics and functionality toggles default to ON with an opt-out control.
     */
    private function get_uk_duaa_config() {
        return array(
            // Advertising still requires explicit consent
            'require_consent' => true,
            
            // Reject button equally prominent (for advertising consent)
            'show_reject_button' => true,
            'reject_button_prominence' => 'equal',
            
            // Show customize button so users can opt out of exempt categories
            'show_customize_button' => true,
            
            // No auto-accept
            'auto_accept_on_scroll' => false,
            'auto_accept_on_click' => false,
            
            // Show categories — analytics and preferences default ON (DUAA exempt)
            'show_categories' => true,
            
            // DUAA-specific: mark exempt categories so the banner JS can default them on
            'duaa_exempt_categories' => array('analytics', 'preferences'),
            'duaa_consent_required_categories' => array('marketing'),
            
            // UK-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_uk_heading', 'Your privacy choices'),
            'banner_description' => get_option('mbr_cc_geolocation_uk_description',
                'We use cookies to improve your experience. Analytics and preference cookies are used under UK DUAA exemptions. Advertising cookies require your consent. You can manage your choices or opt out at any time.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * US Multi-State Configuration
     *
     * As of January 2026, 20 US states have comprehensive privacy laws.
     * Key requirements across the landscape:
     *
     * - "Do Not Sell or Share My Personal Information" link (CCPA/CPRA).
     * - Global Privacy Control (GPC) signal must be honoured in 12+ states.
     * - Opt-out based model (not opt-in) for most states.
     * - California requires visible "Opt-Out Request Honored" confirmation.
     * - Sensitive data processing requires opt-in in 16+ states.
     *
     * GPC signal handling is managed by class-mbr-cc-gpc-handler.php.
     */
    private function get_us_multi_config() {
        return array(
            // US is opt-out based
            'require_consent' => false,
            
            // Can use implied consent
            'auto_accept_on_scroll' => get_option('mbr_cc_ccpa_auto_accept', false),
            
            // Show "Do Not Sell or Share" link prominently (CCPA/CPRA mandate)
            'enable_ccpa' => true,
            'ccpa_link_text' => get_option('mbr_cc_ccpa_link_text', 'Do Not Sell or Share My Personal Information'),
            
            // Reject button not typically needed — "Do Not Sell" covers opt-out
            'show_reject_button' => false,
            
            // Show customize for granular control
            'show_customize_button' => true,
            
            // GPC support flag — the GPC handler reads this
            'gpc_enabled' => true,
            
            // US-specific text (falls back to legacy ccpa option keys)
            'banner_heading' => get_option('mbr_cc_geolocation_us_heading', get_option('mbr_cc_geolocation_ccpa_heading', 'Your Privacy Rights')),
            'banner_description' => get_option('mbr_cc_geolocation_us_description', get_option('mbr_cc_geolocation_ccpa_description',
                'We use cookies and similar technologies. You can opt out of the sale or sharing of your personal information by clicking "Do Not Sell or Share My Personal Information". We honour Global Privacy Control (GPC) signals automatically.'
            )),
        );
    }
    
    /**
     * LGPD (Brazil) Configuration
     */
    private function get_lgpd_config() {
        return array(
            // LGPD similar to GDPR
            'require_consent' => true,
            
            // Equal reject button
            'show_reject_button' => true,
            'reject_button_prominence' => 'equal',
            
            // Show customize button
            'show_customize_button' => true,
            
            // No auto-accept
            'auto_accept_on_scroll' => false,
            'auto_accept_on_click' => false,
            
            // LGPD-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_lgpd_heading', 'Nós valorizamos sua privacidade'),
            'banner_description' => get_option('mbr_cc_geolocation_lgpd_description',
                'Usamos cookies para melhorar sua experiência. Ao clicar em "Aceitar", você concorda com o uso de cookies.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * PIPEDA (Canada) Configuration
     */
    private function get_pipeda_config() {
        return array(
            // PIPEDA requires meaningful consent
            'require_consent' => true,
            
            // Show reject button
            'show_reject_button' => true,
            
            // Show customize button
            'show_customize_button' => true,
            
            // Canada-specific text
            'banner_heading' => get_option('mbr_cc_geolocation_pipeda_heading', 'Your Privacy Matters'),
            'banner_description' => get_option('mbr_cc_geolocation_pipeda_description',
                'We use cookies to enhance your browsing experience. You can accept, reject, or customize your cookie preferences.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * India (DPDP Act 2023) Configuration
     *
     * The Digital Personal Data Protection Act 2023 enters enforcement in phases:
     * - Consent Manager registration opens November 2026.
     * - Full compliance mandatory by May 2027.
     *
     * Requirements:
     * - Standalone privacy notice.
     * - Granular consent with one-click withdrawal.
     * - Verifiable parental consent for minors.
     * - Only India-incorporated entities can register as Consent Managers.
     *
     * The plugin provides the consent interface; registration as a Consent Manager
     * is the site owner's responsibility and requires an India-incorporated entity.
     */
    private function get_india_dpdp_config() {
        return array(
            // DPDP requires explicit consent
            'require_consent' => true,
            
            // Reject/withdraw must be as easy as giving consent
            'show_reject_button' => true,
            'reject_button_prominence' => 'equal',
            
            // Show granular categories
            'show_customize_button' => true,
            'show_categories' => true,
            
            // No auto-accept
            'auto_accept_on_scroll' => false,
            'auto_accept_on_click' => false,
            
            // India-specific text (English default; Hindi/regional can be set in admin)
            'banner_heading' => get_option('mbr_cc_geolocation_india_heading', 'Your Privacy Matters'),
            'banner_description' => get_option('mbr_cc_geolocation_india_description',
                'We use cookies and process personal data to improve your experience. Under India\'s Digital Personal Data Protection Act, we need your consent before processing non-essential data. You can withdraw consent at any time.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * Default (Rest of World) Configuration
     */
    private function get_default_config() {
        return array(
            // Lenient for other regions
            'require_consent' => get_option('mbr_cc_geolocation_default_require', false),
            
            // Can use implied consent
            'auto_accept_on_scroll' => get_option('mbr_cc_default_auto_accept', true),
            
            // Simpler banner
            'show_reject_button' => get_option('mbr_cc_default_show_reject', false),
            'show_customize_button' => get_option('mbr_cc_default_show_customize', true),
            
            // Default text
            'banner_heading' => get_option('mbr_cc_banner_heading', 'We use cookies'),
            'banner_description' => get_option('mbr_cc_banner_description',
                'We use cookies to enhance your browsing experience. By continuing to use this site, you accept our use of cookies.'
            ),
            
            'enable_ccpa' => false,
        );
    }
    
    /**
     * Get current region configuration
     */
    public function get_current_config() {
        $config = array();
        $region = $this->geo->get_region();
        
        // Map legacy keys
        $legacy_map = array(
            'eu_uk' => 'eu_gdpr',
            'ccpa'  => 'us_multi',
        );
        if (isset($legacy_map[$region])) {
            $region = $legacy_map[$region];
        }
        
        $method = "get_{$region}_config";
        if (method_exists($this, $method)) {
            $config = $this->$method();
        } else {
            $config = $this->get_default_config();
        }
        
        return $config;
    }
    
    /**
     * Get region compliance info
     */
    public function get_compliance_info($region = null) {
        if ($region === null) {
            $region = $this->geo->get_region();
        }
        
        $info = array(
            'eu_gdpr' => array(
                'name' => 'EU GDPR / ePrivacy',
                'law' => 'General Data Protection Regulation + ePrivacy Directive',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Explicit opt-in consent required for all non-essential cookies',
                    'Reject button must be equally prominent as accept',
                    'Pre-ticked boxes not allowed',
                    'Cookie categories must be shown',
                    'Withdrawal of consent must be as easy as giving it',
                ),
                'penalties' => 'Up to €20 million or 4% of annual global turnover',
            ),
            'uk_duaa' => array(
                'name' => 'UK GDPR + DUAA 2025',
                'law' => 'UK General Data Protection Regulation + Data Use and Access Act 2025',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Analytics cookies exempt from consent (DUAA Schedule A1) — opt-out required',
                    'Functionality/appearance cookies exempt — opt-out required',
                    'Advertising/marketing cookies still require explicit consent',
                    'Clear information and easy opt-out for all cookie categories',
                    'PECR fines now match UK GDPR: up to £17.5M or 4% of turnover',
                    'Formal complaints procedure required by June 2026',
                ),
                'penalties' => 'Up to £17.5 million or 4% of annual global turnover',
            ),
            'us_multi' => array(
                'name' => 'US Multi-State (CCPA + 20 States)',
                'law' => 'California Consumer Privacy Act/CPRA + 19 additional state privacy laws',
                'requires_consent' => false,
                'key_requirements' => array(
                    'Must provide "Do Not Sell or Share My Personal Information" link (CCPA)',
                    'Opt-out based model — not opt-in',
                    'Must honour Global Privacy Control (GPC) signals (12+ states)',
                    'California: visible confirmation when GPC opt-out is processed',
                    'Sensitive data requires opt-in consent in 16+ states',
                    'Users can request data deletion',
                ),
                'penalties' => 'Up to $7,988 per intentional violation (CA); varies by state',
            ),
            'lgpd' => array(
                'name' => 'Brazil LGPD',
                'law' => 'Lei Geral de Proteção de Dados',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Clear and specific consent required',
                    'Must show legitimate purpose',
                    'Users can revoke consent',
                    'Data minimization required',
                    'Similar to GDPR requirements',
                ),
                'penalties' => 'Up to 2% of revenue (max R$50 million per violation)',
            ),
            'pipeda' => array(
                'name' => 'Canada PIPEDA / CASL',
                'law' => 'Personal Information Protection and Electronic Documents Act + Canadian Anti-Spam Law',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Meaningful consent required',
                    'Must identify purpose before collection',
                    'Implied consent allowed in some low-risk cases',
                    'CASL classifies cookies as "computer programs" requiring consent',
                    'Quebec requires express opt-in',
                    'Bill C-27 (CPPA) may introduce stricter rules — monitor progress',
                ),
                'penalties' => 'Up to $10 million CAD (PIPEDA); $10M per violation (CASL)',
            ),
            'india_dpdp' => array(
                'name' => 'India DPDP Act',
                'law' => 'Digital Personal Data Protection Act 2023',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Standalone privacy notice required',
                    'Granular consent with one-click withdrawal',
                    'Verifiable parental consent for minors',
                    'Consent Manager registration opens November 2026 (India-incorporated only)',
                    'Full compliance mandatory by May 2027',
                    'Automated deletion with proof required',
                ),
                'penalties' => 'Up to ₹250 crore (approx. £25M) per violation',
            ),
            'default' => array(
                'name' => 'General Best Practices',
                'law' => 'No specific regulation',
                'requires_consent' => false,
                'key_requirements' => array(
                    'Transparent about cookie usage',
                    'Provide privacy policy',
                    'Allow users to manage preferences',
                    'Respect user choices',
                ),
                'penalties' => 'Varies by jurisdiction',
            ),
            // Legacy keys — kept for backwards compatibility.
            'eu_uk' => array(
                'name' => 'EU/UK GDPR (Legacy)',
                'law' => 'General Data Protection Regulation',
                'requires_consent' => true,
                'key_requirements' => array(
                    'Explicit opt-in consent required',
                    'Reject button must be equally prominent',
                    'Pre-ticked boxes not allowed',
                    'Cookie categories must be shown',
                    'Withdrawal of consent must be easy',
                ),
                'penalties' => 'Up to €20 million or 4% of annual turnover',
            ),
            'ccpa' => array(
                'name' => 'California CCPA (Legacy)',
                'law' => 'California Consumer Privacy Act',
                'requires_consent' => false,
                'key_requirements' => array(
                    'Must provide "Do Not Sell" link',
                    'Opt-out based (not opt-in)',
                    'Must honor opt-out requests',
                    'Must disclose data collection practices',
                    'Users can request data deletion',
                ),
                'penalties' => 'Up to $7,500 per violation',
            ),
        );
        
        return isset($info[$region]) ? $info[$region] : $info['default'];
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', function() {
    MBR_CC_Region_Config::get_instance();
}, 10);

// Helper function to get instance
function mbr_cc_region_config() {
    return MBR_CC_Region_Config::get_instance();
}
