<?php
/**
 * A/B Testing for Banner Position
 *
 * Randomly assigns visitors to one of three banner position variants:
 *   A — bottom bar  (control — mirrors current default)
 *   B — popup       (centred modal with overlay)
 *   C — box-left    (floating box, bottom-left)
 *
 * Assignment is stored in a session cookie so the same visitor always sees
 * the same variant. Impressions and accept-all conversions are tracked per
 * variant in wp_options. The winner (highest accept-all rate) can be promoted
 * to the live setting with a single button click in wp-admin.
 *
 * @package MBR_Cookie_Consent
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class MBR_CC_AB_Testing {

    private static $instance = null;

    /** Cookie name used to persist variant assignment across page views. */
    const ASSIGNMENT_COOKIE = 'mbr_cc_ab_variant';

    /** Option key prefix for stats storage. */
    const STATS_OPTION = 'mbr_cc_ab_stats';

    /** Available variants: key => position value used by the banner. */
    const VARIANTS = array(
        'a' => 'bottom',
        'b' => 'popup',
        'c' => 'box-left',
    );

    /** Human-readable variant labels for the admin UI. */
    const VARIANT_LABELS = array(
        'a' => 'A — Bottom bar',
        'b' => 'B — Popup',
        'c' => 'C — Box left',
    );

    /**
     * The settings a variant corresponds to once promoted.
     *
     * VARIANTS above holds the single class token the test swaps, which is all
     * a running test needs. Promotion is different: it writes real settings, and
     * the banner is configured as a position AND a layout — the appearance
     * picker offers pairs. Writing 'popup' or 'box-left' into the position
     * option, as promotion used to, produced a combination the picker cannot
     * represent, so the appearance screen showed no layout selected at all and
     * the next save on that screen silently reset it.
     *
     * @var array Variant key => array( position, layout ).
     */
    const VARIANT_SETTINGS = array(
        'a' => array( 'position' => 'bottom', 'layout' => 'bar' ),
        'b' => array( 'position' => 'bottom', 'layout' => 'popup' ),
        'c' => array( 'position' => 'bottom', 'layout' => 'box-left' ),
    );

    private function __construct() {
        if ( ! get_option( 'mbr_cc_ab_testing_enabled', false ) ) {
            return;
        }

        // Variant assignment and the banner position it implies are deliberately
        // NOT done here any more. Both used to run on the server — assign_variant()
        // on init, and a filter on option_mbr_cc_banner_position — which made the
        // rendered HTML depend on the visitor. Behind a full-page cache that is the
        // same class of fault 2.3.4 removed from consent, regional wording and GPC:
        // whichever visitor happened to prime the cache had their variant stored in
        // it, and everybody served that copy afterwards saw that variant rather than
        // their own. The impression was then logged against it too, so the figures
        // measured the cache, not the visitors.
        //
        // Assignment now happens in the browser, in enqueue_tracker() below, and the
        // document stays identical for everyone.

        // Track impressions via AJAX (fired by JS when banner is shown).
        add_action( 'wp_ajax_mbr_cc_ab_impression',        array( $this, 'ajax_track_impression' ) );
        add_action( 'wp_ajax_nopriv_mbr_cc_ab_impression', array( $this, 'ajax_track_impression' ) );

        // Track accept-all conversions (fires on mbr_cc_consent_saved).
        add_action( 'wp_ajax_mbr_cc_ab_conversion',        array( $this, 'ajax_track_conversion' ) );
        add_action( 'wp_ajax_nopriv_mbr_cc_ab_conversion', array( $this, 'ajax_track_conversion' ) );

        // Enqueue the tiny tracker script on the frontend.
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_tracker' ) );

        // Admin AJAX: promote winner.
        add_action( 'wp_ajax_mbr_cc_ab_promote_winner', array( $this, 'ajax_promote_winner' ) );

        // Admin AJAX: reset stats.
        add_action( 'wp_ajax_mbr_cc_ab_reset_stats', array( $this, 'ajax_reset_stats' ) );
    }

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    // ── Variant assignment ────────────────────────────────────────────────

    /**
     * Variant assignment and banner-position override used to live here, as
     * assign_variant() and override_position(). They are gone rather than
     * deprecated: both wrote a per-visitor value into a document that a page
     * cache stores once and serves to everyone, so neither could be made
     * correct while it ran on the server. The browser does the work now — see
     * enqueue_tracker().
     *
     * current_variant() has gone with them. It read $_COOKIE, so every caller
     * on the render path was a cache hazard by construction; the tracker script
     * reports the variant the browser actually applied instead.
     */

    // ── Tracking ──────────────────────────────────────────────────────────

    /**
     * Get stats array, initialised with zeros if missing.
     *
     * @return array  [ variant => [ impressions => int, conversions => int ] ]
     */
    /**
     * Option name holding a single counter.
     *
     * @param string $variant Variant key.
     * @param string $field   'impressions' or 'conversions'.
     * @return string
     */
    private static function counter_option( $variant, $field ) {
        return 'mbr_cc_ab_count_' . sanitize_key( $variant ) . '_' . sanitize_key( $field );
    }

    public static function get_stats() {
        self::migrate_legacy_stats();

        $stats = array();

        foreach ( array_keys( self::VARIANTS ) as $key ) {
            $stats[ $key ] = array(
                'impressions' => (int) get_option( self::counter_option( $key, 'impressions' ), 0 ),
                'conversions' => (int) get_option( self::counter_option( $key, 'conversions' ), 0 ),
            );
        }

        return $stats;
    }

    /**
     * Move the old single-blob counters into individual options.
     *
     * Runs once, the first time stats are read after updating. Existing figures
     * are carried across rather than discarded.
     *
     * @return void
     */
    private static function migrate_legacy_stats() {
        $legacy = get_option( self::STATS_OPTION, null );

        if ( ! is_array( $legacy ) ) {
            return;
        }

        foreach ( $legacy as $variant => $fields ) {
            if ( ! is_array( $fields ) ) {
                continue;
            }

            foreach ( array( 'impressions', 'conversions' ) as $field ) {
                if ( isset( $fields[ $field ] ) ) {
                    update_option( self::counter_option( $variant, $field ), (int) $fields[ $field ], false );
                }
            }
        }

        delete_option( self::STATS_OPTION );
    }

    /**
     * Add one to a counter, atomically.
     *
     * These endpoints are unauthenticated and fire on ordinary page views, so
     * two visitors can arrive at the same instant. Reading the value into PHP,
     * adding one and writing it back loses an increment whenever that happens:
     * the second write is based on a figure already stale by the time it lands.
     *
     * A single UPDATE performing the arithmetic in the database is atomic, so
     * concurrent requests queue rather than overwrite one another. Each counter
     * therefore lives in its own option holding a plain integer, rather than all
     * of them sharing one serialised array.
     *
     * @param string $variant Variant key.
     * @param string $field   'impressions' or 'conversions'.
     * @return void
     */
    private static function increment( $variant, $field ) {
        global $wpdb;

        $option = self::counter_option( $variant, $field );

        $updated = $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
                $option
            )
        );

        if ( $updated ) {
            // The row is now stale in the object cache, which get_option reads first.
            wp_cache_delete( $option, 'options' );
            wp_cache_delete( 'alloptions', 'options' );
            return;
        }

        // No such row yet. option_name is unique, so if two requests race to
        // create it the loser simply falls through to the UPDATE below and its
        // increment is still counted.
        if ( ! add_option( $option, 1, '', false ) ) {
            $wpdb->query(
                $wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value = option_value + 1 WHERE option_name = %s",
                    $option
                )
            );
            wp_cache_delete( $option, 'options' );
            wp_cache_delete( 'alloptions', 'options' );
        }
    }

    /**
     * Deduplicate a tracking event per visitor.
     *
     * These endpoints are unauthenticated and fired via sendBeacon, so a nonce
     * baked into cached HTML would go stale and silently break tracking. The
     * dedupe window is the real control: it bounds how much an attacker can
     * skew a test or drive option writes, and it also fixes the accuracy bug
     * where one visitor reloading a page logged an impression every time.
     *
     * @param string $event   Event name, e.g. 'impression'.
     * @param string $variant Variant key.
     * @param int    $window  Dedupe window in seconds.
     * @return bool True if the event should be counted.
     */
    private static function should_count( $event, $variant, $window ) {
        $ip = function_exists( 'mbr_cc_get_client_ip' ) ? mbr_cc_get_client_ip() : '';

        if ( '' === $ip ) {
            return true;
        }

        $key = 'mbr_cc_ab_' . hash( 'sha256', $event . '|' . $variant . '|' . $ip . wp_salt( 'auth' ) );

        if ( get_transient( $key ) ) {
            return false;
        }

        set_transient( $key, 1, $window );

        return true;
    }

    /** AJAX: record a banner impression. */
    public function ajax_track_impression() {
        $variant = isset( $_POST['variant'] ) ? sanitize_key( wp_unslash( $_POST['variant'] ) ) : '';

        if ( ! array_key_exists( $variant, self::VARIANTS ) ) {
            wp_send_json_success();
        }

        /** Filter the impression dedupe window. @since 2.3.1 */
        $window = (int) apply_filters( 'mbr_cc_ab_impression_window', HOUR_IN_SECONDS );

        if ( self::should_count( 'impression', $variant, $window ) ) {
            self::increment( $variant, 'impressions' );
        }

        wp_send_json_success();
    }

    /** AJAX: record an accept-all conversion. */
    public function ajax_track_conversion() {
        $variant = isset( $_POST['variant'] ) ? sanitize_key( wp_unslash( $_POST['variant'] ) ) : '';

        if ( ! array_key_exists( $variant, self::VARIANTS ) ) {
            wp_send_json_success();
        }

        /** Filter the conversion dedupe window. @since 2.3.1 */
        $window = (int) apply_filters( 'mbr_cc_ab_conversion_window', DAY_IN_SECONDS );

        if ( self::should_count( 'conversion', $variant, $window ) ) {
            self::increment( $variant, 'conversions' );
        }

        wp_send_json_success();
    }

    // ── Admin actions ─────────────────────────────────────────────────────

    /** AJAX: promote winning variant to the live position setting. */
    public function ajax_promote_winner() {
        check_ajax_referer( 'mbr_cc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }

        $winner = self::get_winner();
        if ( ! $winner ) {
            wp_send_json_error( array( 'message' => 'No winner determined yet — not enough data.' ) );
        }

        $settings = isset( self::VARIANT_SETTINGS[ $winner ] )
            ? self::VARIANT_SETTINGS[ $winner ]
            : array( 'position' => 'bottom', 'layout' => 'bar' );

        update_option( 'mbr_cc_banner_position', $settings['position'] );
        update_option( 'mbr_cc_banner_layout', $settings['layout'] );
        update_option( 'mbr_cc_ab_testing_enabled', false );

        // The banner markup carries these, so a cached front end keeps serving
        // the losing layout until something clears it.
        if ( class_exists( 'MBR_CC_Cache' ) ) {
            MBR_CC_Cache::flush( 'ab-promote' );
        }

        wp_send_json_success( array(
            'message'  => sprintf(
                /* translators: 1: promoted variant label, 2: banner layout the variant maps to. */
                __( 'Variant %1$s promoted. Banner layout set to "%2$s". A/B testing disabled.', 'mbr-cookie-consent' ),
                strtoupper( $winner ),
                $settings['layout']
            ),
            'position' => $settings['position'],
            'layout'   => $settings['layout'],
        ) );
    }

    /** AJAX: reset all A/B stats. */
    public function ajax_reset_stats() {
        check_ajax_referer( 'mbr_cc_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorized.' ) );
        }
        delete_option( self::STATS_OPTION );

        foreach ( array_keys( self::VARIANTS ) as $key ) {
            delete_option( self::counter_option( $key, 'impressions' ) );
            delete_option( self::counter_option( $key, 'conversions' ) );
        }

        wp_send_json_success( array( 'message' => __( 'A/B test stats reset.', 'mbr-cookie-consent' ) ) );
    }

    /**
     * Return the variant key with the highest accept-all rate, or null if
     * no variant has enough impressions (minimum 10) to be meaningful.
     *
     * @return string|null
     */
    public static function get_winner() {
        $stats      = self::get_stats();
        $best_key   = null;
        $best_rate  = -1;

        foreach ( $stats as $key => $data ) {
            if ( $data['impressions'] < 10 ) {
                continue;
            }
            $rate = $data['impressions'] > 0
                ? $data['conversions'] / $data['impressions']
                : 0;
            if ( $rate > $best_rate ) {
                $best_rate = $rate;
                $best_key  = $key;
            }
        }

        return $best_key;
    }

    // ── Frontend tracker script ───────────────────────────────────────────

    public function enqueue_tracker() {
        if ( is_admin() ) {
            return;
        }

        // Everything passed to the script below is a site-wide setting. Nothing
        // here varies by visitor, so the inline script is identical in every
        // copy of the page and safe for a cache to store — which is the whole
        // point of moving assignment into the browser.
        //
        // The site's own banner position is passed so the script can swap that
        // one class token for the variant's. Rebuilding the class list here
        // would be guesswork: the banner carries a position class and a layout
        // class, they share a prefix, and some variant values ('popup',
        // 'box-left') are also layout names. Naming the token to replace keeps
        // the swap exact, and identical to what the server-side filter did.
        $data = array(
            'variants'     => self::VARIANTS,
            'cookie'       => self::ASSIGNMENT_COOKIE,
            'sitePosition' => (string) get_option( 'mbr_cc_banner_position', 'bottom' ),
            'cookiePath'   => defined( 'COOKIEPATH' ) && COOKIEPATH ? COOKIEPATH : '/',
            'cookieDomain' => defined( 'COOKIE_DOMAIN' ) && COOKIE_DOMAIN ? COOKIE_DOMAIN : '',
            'secure'       => is_ssl(),
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
        );

        $script = '
(function() {
    var cfg = ' . wp_json_encode( $data ) . ';
    var keys = Object.keys(cfg.variants);

    function readCookie(name) {
        var parts = document.cookie ? document.cookie.split(";") : [];
        for (var i = 0; i < parts.length; i++) {
            var p = parts[i].trim();
            if (p.indexOf(name + "=") === 0) {
                return decodeURIComponent(p.substring(name.length + 1));
            }
        }
        return null;
    }

    function writeCookie(name, value) {
        // Session cookie: no Expires/Max-Age, so it clears when the browser
        // closes, exactly as the server-set one did. It cannot be HttpOnly any
        // more, because the script that assigns it has to be able to read it
        // back. It holds a single letter identifying a banner layout and no
        // personal data, so that costs nothing.
        var c = name + "=" + encodeURIComponent(value) + "; path=" + cfg.cookiePath + "; SameSite=Lax";
        if (cfg.cookieDomain) { c += "; domain=" + cfg.cookieDomain; }
        if (cfg.secure) { c += "; Secure"; }
        try { document.cookie = c; } catch (e) {}
    }

    // Assign, or recover an existing assignment. A visitor keeps their variant
    // across page views; a value we do not recognise is replaced rather than
    // trusted.
    var variant = readCookie(cfg.cookie);
    if (keys.indexOf(variant) === -1) {
        variant = keys[Math.floor(Math.random() * keys.length)];
        writeCookie(cfg.cookie, variant);
    }

    // Apply the variant by swapping the one position class. The banner is
    // rendered display:none and revealed by banner.js, so this lands before
    // anything is visible and there is no flash of the wrong layout.
    function applyVariant() {
        var banner = document.getElementById("mbr-cc-banner");
        if (!banner) { return null; }
        var target = cfg.variants[variant];
        if (target && target !== cfg.sitePosition) {
            banner.classList.remove("mbr-cc-banner--" + cfg.sitePosition);
            banner.classList.add("mbr-cc-banner--" + target);
        }
        return banner;
    }

    function post(action) {
        var fd = new FormData();
        fd.append("action", action);
        fd.append("variant", variant);
        navigator.sendBeacon ? navigator.sendBeacon(cfg.ajaxUrl, fd)
            : fetch(cfg.ajaxUrl, { method: "POST", body: fd });
    }

    function watch(banner) {
        if (!banner) { return; }
        var obs = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                if (m.type === "attributes" && m.attributeName === "style") {
                    if (banner.style.display !== "none" && banner.offsetParent !== null) {
                        post("mbr_cc_ab_impression");
                        obs.disconnect();
                    }
                }
            });
        });
        obs.observe(banner, { attributes: true });
        // Also catch it if already visible.
        if (banner.style.display !== "none" && getComputedStyle(banner).display !== "none") {
            post("mbr_cc_ab_impression");
            obs.disconnect();
        }
    }

    // This script is enqueued in the footer, so the banner is normally already
    // parsed. The listener is a fallback for anything that moves it earlier.
    var el = applyVariant();
    if (el) {
        watch(el);
    } else {
        document.addEventListener("DOMContentLoaded", function() {
            watch(applyVariant());
        });
    }

    // Track accept-all conversion.
    document.addEventListener("mbr_cc_consent_saved", function(e) {
        var consent = e.detail && e.detail[0];
        if (consent && (consent.all === true)) {
            post("mbr_cc_ab_conversion");
        }
    });
    // jQuery event fallback.
    if (window.jQuery) {
        jQuery(document).on("mbr_cc_consent_saved", function(e, consent) {
            if (consent && consent.all === true) {
                post("mbr_cc_ab_conversion");
            }
        });
    }
})();
';
        wp_add_inline_script( 'mbr-cc-banner', $script );
    }

    /** @return bool */
    public static function is_enabled() {
        return (bool) get_option( 'mbr_cc_ab_testing_enabled', false );
    }
}
