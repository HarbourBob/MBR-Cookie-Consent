<?php
/**
 * Google Consent Mode v2 and Microsoft UET Consent Mode integration.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Consent Modes class - handles Google and Microsoft consent mode integrations.
 */
class MBR_CC_Consent_Modes {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Consent_Modes
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Consent_Modes
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
        // Only load on frontend.
        if (!is_admin()) {
            add_action('wp_head', array($this, 'output_consent_mode_scripts'), 1);
        }
    }
    
    /**
     * Output Google Consent Mode v2 and Microsoft UET Consent Mode defaults.
     *
     * This must load BEFORE Google Analytics, Google Ads and Microsoft UET tags.
     *
     * Everything printed here is derived from site options only. No cookie is
     * read, so the markup is identical for every visitor and safe for a page
     * cache to store. The visitor's own decision is applied by the update call
     * at the foot of the script, from the cookie, in the browser.
     *
     * That split is also how Consent Mode is specified to be used: 'default'
     * establishes the pre-consent baseline, 'update' carries the decision.
     * This method previously read the consent cookie and wrote the answer into
     * 'default', which put per-visitor state into a shared document — so on any
     * cached site the first consenting visitor's 'granted' was stored in the
     * cached HTML and served to everyone who came after, whatever they had
     * chosen. Google was told those visitors had consented when they had not.
     *
     * @return void
     */
    public function output_consent_mode_scripts() {
        $google_enabled    = (bool) get_option('mbr_cc_google_consent_mode', false);
        $microsoft_enabled = (bool) get_option('mbr_cc_microsoft_consent_mode', false);

        // Nothing to say if neither framework is enabled. An empty script block
        // was previously printed into the head of every page regardless.
        if (!$google_enabled && !$microsoft_enabled) {
            return;
        }

        // Site-level defaults. Constant across visitors, therefore cacheable.
        $google_default    = get_option('mbr_cc_google_default_deny', true) ? 'denied' : 'granted';
        $microsoft_default = get_option('mbr_cc_microsoft_default_deny', true) ? 'denied' : 'granted';
        $redaction         = (bool) get_option('mbr_cc_google_ads_redaction', true);
        $passthrough       = (bool) get_option('mbr_cc_google_url_passthrough', false);

        // The site's GPC policy. Needed here, not just in banner.js, because
        // this script runs first — see applyStoredConsent() below.
        $gpc_policy = class_exists('MBR_CC_GPC_Handler')
            ? MBR_CC_GPC_Handler::get_instance()->get_client_policy()
            : array('enabled' => false, 'suppress' => array());
        ?>
        <!-- MBR Cookie Consent - Consent Mode Integration -->
        <script data-mbr-cc-consent-mode="true">
        <?php if ($google_enabled) : ?>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}

        gtag('consent', 'default', {
            'ad_storage': '<?php echo esc_js($google_default); ?>',
            'ad_user_data': '<?php echo esc_js($google_default); ?>',
            'ad_personalization': '<?php echo esc_js($google_default); ?>',
            'analytics_storage': '<?php echo esc_js($google_default); ?>',
            'functionality_storage': '<?php echo esc_js($google_default); ?>',
            'personalization_storage': '<?php echo esc_js($google_default); ?>',
            'security_storage': 'granted',
            'wait_for_update': 500
        });
        <?php if ($redaction) : ?>
        gtag('set', 'ads_data_redaction', <?php echo 'denied' === $google_default ? 'true' : 'false'; ?>);
        <?php endif; ?>
        <?php if ($passthrough) : ?>
        gtag('set', 'url_passthrough', true);
        <?php endif; ?>
        <?php endif; ?>

        <?php if ($microsoft_enabled) : ?>
        window.uetq = window.uetq || [];
        window.uetq.push('consent', 'default', {
            'ad_storage': '<?php echo esc_js($microsoft_default); ?>'
        });
        <?php endif; ?>

        window.MbrCcConsentModes = {

            // Site policy, identical for every visitor and so cache-safe.
            gpc: <?php echo wp_json_encode($gpc_policy); ?>,
            defaults: {
                google: '<?php echo esc_js($google_default); ?>',
                microsoft: '<?php echo esc_js($microsoft_default); ?>'
            },

            /**
             * Read the stored consent choice from the cookie.
             *
             * Returns null when there is no cookie, when it is implausibly
             * large, or when it does not parse — in every one of those cases
             * the default state above stands, which is the safe outcome.
             */
            readStoredConsent: function () {
                var name  = 'mbr_cc_consent=';
                var parts = document.cookie ? document.cookie.split(';') : [];

                for (var i = 0; i < parts.length; i++) {
                    var part = parts[i];
                    while (part.charAt(0) === ' ') { part = part.substring(1); }
                    if (part.indexOf(name) !== 0) { continue; }

                    var raw = part.substring(name.length);
                    if (!raw || raw.length > 2048) { return null; }

                    // The banner writes the value unencoded, but a proxy or an
                    // older release may have encoded it. Try to decode, and
                    // fall back to the raw value if that fails.
                    try { raw = decodeURIComponent(raw); } catch (e) {}

                    try {
                        var parsed = JSON.parse(raw);
                        return (parsed && typeof parsed === 'object') ? parsed : null;
                    } catch (e) {
                        return null;
                    }
                }

                return null;
            },

            /**
             * Mirrors the server-side rule: an explicit "accept all" grants
             * every category, otherwise the category must be true in its own
             * right. Anything absent counts as not granted.
             */
            hasCategory: function (consent, category) {
                if (!consent) { return false; }
                if (consent.all === true) { return true; }
                return consent[category] === true;
            },

            updateGoogleConsent: function (consent) {
                <?php if ($google_enabled) : ?>
                if (typeof gtag !== 'function') { return; }

                var marketing   = this.hasCategory(consent, 'marketing');
                var analytics   = this.hasCategory(consent, 'analytics');
                var preferences = this.hasCategory(consent, 'preferences');

                gtag('consent', 'update', {
                    'ad_storage': marketing ? 'granted' : 'denied',
                    'ad_user_data': marketing ? 'granted' : 'denied',
                    'ad_personalization': marketing ? 'granted' : 'denied',
                    'analytics_storage': analytics ? 'granted' : 'denied',
                    'functionality_storage': preferences ? 'granted' : 'denied',
                    'personalization_storage': preferences ? 'granted' : 'denied'
                });

                <?php if ($redaction) : ?>
                gtag('set', 'ads_data_redaction', !marketing);
                <?php endif; ?>
                <?php endif; ?>
            },

            updateMicrosoftConsent: function (consent) {
                <?php if ($microsoft_enabled) : ?>
                if (typeof window.uetq === 'undefined') { return; }

                window.uetq.push('consent', 'update', {
                    'ad_storage': this.hasCategory(consent, 'marketing') ? 'granted' : 'denied'
                });
                <?php endif; ?>
            },

            updateAllConsent: function (consent) {
                this.updateGoogleConsent(consent);
                this.updateMicrosoftConsent(consent);
            },

            /**
             * Is this visitor sending Global Privacy Control, and does the
             * site honour it? Read in the browser: the signal is per-visitor.
             */
            gpcActive: function () {
                return !!(this.gpc && this.gpc.enabled)
                    && typeof navigator !== 'undefined'
                    && navigator.globalPrivacyControl === true;
            },

            /**
             * Apply GPC to a consent object, exactly as banner.js does:
             * suppressed categories forced off, and a blanket "all" replaced
             * by each remaining category granted in its own right.
             */
            withGpc: function (consent) {
                var suppress = (this.gpc && this.gpc.suppress) || ['marketing'];
                var out = {}, key, i;
                var all = consent && consent.all === true;

                for (key in consent) {
                    if (Object.prototype.hasOwnProperty.call(consent, key) && key !== 'all') {
                        out[key] = consent[key];
                    }
                }
                if (all) {
                    out.marketing = out.analytics = out.preferences = true;
                }
                for (i = 0; i < suppress.length; i++) {
                    out[suppress[i]] = false;
                }
                return out;
            },

            /**
             * Apply whatever the visitor has already chosen.
             *
             * Runs synchronously in this same script block, so a returning
             * visitor's real state is in place before any Google or Microsoft
             * tag has had the chance to load.
             *
             * CHANGED IN 2.6.0 — GPC is applied here too. It used to be left to
             * banner.js, which loads later: a returning visitor with a stored
             * marketing grant and GPC switched on had ad_storage 'granted'
             * sent first and corrected afterwards, and any tag that fired in
             * between was told the visitor had consented to advertising.
             * A site whose Consent Mode default is 'granted' had the same gap
             * for GPC visitors with no stored choice at all.
             */
            applyStoredConsent: function () {
                var consent = this.readStoredConsent();
                var gpc     = this.gpcActive();

                if (consent) {
                    this.updateAllConsent(gpc ? this.withGpc(consent) : consent);
                } else if (gpc) {
                    // No choice yet. Only a 'granted' default needs correcting;
                    // a 'denied' default already says what GPC asks for.
                    var granted = this.withGpc({ all: true });
                    if (this.defaults.google === 'granted') {
                        this.updateGoogleConsent(granted);
                    }
                    if (this.defaults.microsoft === 'granted') {
                        this.updateMicrosoftConsent(granted);
                    }
                }

                return consent;
            }
        };

        window.MbrCcConsentModes.applyStoredConsent();
        </script>
        <?php
    }

    /**
     * Get consent mode settings for admin display.
     *
     * @return array Settings data.
     */
    public static function get_settings() {
        return array(
            'google_enabled' => get_option('mbr_cc_google_consent_mode', false),
            'google_default_deny' => get_option('mbr_cc_google_default_deny', true),
            'google_ads_redaction' => get_option('mbr_cc_google_ads_redaction', true),
            'google_url_passthrough' => get_option('mbr_cc_google_url_passthrough', false),
            'microsoft_enabled' => get_option('mbr_cc_microsoft_consent_mode', false),
            'microsoft_default_deny' => get_option('mbr_cc_microsoft_default_deny', true),
        );
    }
}
