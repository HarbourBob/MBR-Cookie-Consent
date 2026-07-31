<?php
/**
 * Script Blocker
 *
 * Prevents non-consented scripts and iframes from loading, and optionally
 * replaces blocked iframes with a branded placeholder overlay.
 *
 * Built-in service definitions cover the most common third-party embeds so
 * site owners do not need to configure them manually. Custom entries added
 * via the Scanner screen are merged on top.
 *
 * @package MBR_Cookie_Consent
 * @since   1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class MBR_CC_Script_Blocker
 */
class MBR_CC_Script_Blocker {

    /** @var MBR_CC_Script_Blocker|null */
    private static $instance = null;

    /** @var array Custom blocked-script entries from the database. */
    private $blocked_scripts = array();

    /**
     * Built-in service definitions organised by consent category.
     * Each entry is checked against every <script src="…"> and <iframe src="…">
     * in the page output.
     *
     * 'domains' — URL fragments matched against src attributes.
     * 'type'    — 'script' | 'iframe' | 'both'
     *
     * @var array
     */
    private static $builtin_services = array(

        // ── Marketing ─────────────────────────────────────────────────────
        'marketing' => array(
            array(
                'name'    => 'YouTube',
                'domains' => array( 'youtube.com/embed', 'youtube-nocookie.com/embed', 'youtu.be' ),
                'type'    => 'iframe',
            ),
            array(
                'name'    => 'Google Ads / DoubleClick',
                'domains' => array( 'googleadservices.com', 'doubleclick.net', 'googlesyndication.com' ),
                'type'    => 'both',
            ),
            array(
                'name'    => 'Facebook Pixel',
                'domains' => array( 'connect.facebook.net', 'facebook.com/tr' ),
                'type'    => 'both',
            ),
            array(
                'name'    => 'Twitter / X',
                'domains' => array( 'platform.twitter.com', 'syndication.twitter.com', 'ads-twitter.com' ),
                'type'    => 'both',
            ),
            array(
                'name'    => 'LinkedIn Insight',
                'domains' => array( 'snap.licdn.com', 'linkedin.com/insight' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'TikTok Pixel',
                'domains' => array( 'analytics.tiktok.com' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Pinterest Tag',
                'domains' => array( 'ct.pinterest.com', 'pintrk' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Hotjar',
                'domains' => array( 'static.hotjar.com' ),
                'type'    => 'script',
            ),
        ),

        // ── Analytics ─────────────────────────────────────────────────────
        'analytics' => array(
            array(
                'name'    => 'Google Analytics',
                'domains' => array( 'google-analytics.com', 'googletagmanager.com', 'gtag/js' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Matomo / Piwik',
                'domains' => array( 'matomo.js', 'piwik.js' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Clarity',
                'domains' => array( 'clarity.ms' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Mixpanel',
                'domains' => array( 'cdn.mxpnl.com' ),
                'type'    => 'script',
            ),
        ),

        // ── Preferences ───────────────────────────────────────────────────
        'preferences' => array(
            array(
                'name'    => 'Vimeo',
                'domains' => array( 'player.vimeo.com' ),
                'type'    => 'iframe',
            ),
            array(
                'name'    => 'Google Maps',
                'domains' => array( 'maps.google.com', 'maps.googleapis.com', 'google.com/maps' ),
                'type'    => 'iframe',
            ),
            array(
                'name'    => 'Google Fonts',
                'domains' => array( 'fonts.googleapis.com', 'fonts.gstatic.com' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Spotify Embed',
                'domains' => array( 'open.spotify.com/embed' ),
                'type'    => 'iframe',
            ),
            array(
                'name'    => 'SoundCloud',
                'domains' => array( 'w.soundcloud.com/player' ),
                'type'    => 'iframe',
            ),
            array(
                'name'    => 'Intercom',
                'domains' => array( 'widget.intercom.io', 'js.intercomcdn.com' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'Drift',
                'domains' => array( 'js.driftt.com' ),
                'type'    => 'script',
            ),
            array(
                'name'    => 'HubSpot',
                'domains' => array( 'js.hs-scripts.com', 'js.hsforms.net' ),
                'type'    => 'script',
            ),
        ),
    );

    // ─────────────────────────────────────────────────────────────────────
    // Singleton
    // ─────────────────────────────────────────────────────────────────────

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Hook at template_redirect priority 1 (before most plugins).
        add_action( 'template_redirect', array( $this, 'start_buffer' ), 1 );

        // Tell WP Rocket not to lazy-load iframes that we will be blocking —
        // this prevents WP Rocket transforming src -> data-lazy-src before our
        // buffer processes the HTML, which would cause our regex to miss them.
        add_filter( 'rocket_lazy_load_exclude_iframes', array( $this, 'rocket_exclude_iframes' ) );

        // Tell WP Rocket's new Delay JS / Minify not to touch our assets.
        add_filter( 'rocket_delay_js_exclusions', array( $this, 'rocket_exclude_js' ) );

        $this->load_blocked_scripts();
    }

    /**
     * Tell WP Rocket to skip lazy-loading iframes from services we block.
     * This ensures src attributes are preserved so our regex can match them.
     *
     * @param  array $exclusions Existing WP Rocket iframe exclusions.
     * @return array
     */
    public function rocket_exclude_iframes( $exclusions ) {
        $domains = array();
        foreach ( self::$builtin_services as $services ) {
            foreach ( $services as $service ) {
                if ( 'iframe' === $service['type'] || 'both' === $service['type'] ) {
                    foreach ( $service['domains'] as $domain ) {
                        $domains[] = $domain;
                    }
                }
            }
        }
        // Add custom iframe entries too.
        foreach ( $this->blocked_scripts as $script ) {
            if ( 'iframe' === ( $script['type'] ?? '' ) ) {
                $domains[] = $script['identifier'];
            }
        }
        return array_merge( $exclusions, $domains );
    }

    /**
     * Tell WP Rocket not to delay or minify our consent JS.
     *
     * @param  array $exclusions Existing exclusions.
     * @return array
     */
    public function rocket_exclude_js( $exclusions ) {
        $exclusions[] = 'mbr-cookie-consent';
        $exclusions[] = 'mbr-cc-banner';
        $exclusions[] = 'mbr-cc-blocked-content';
        return $exclusions;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Output buffering
    // ─────────────────────────────────────────────────────────────────────

    private function load_blocked_scripts() {
        $this->blocked_scripts = get_option( 'mbr_cc_blocked_scripts', array() );
    }

    public function start_buffer() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }
        ob_start( array( $this, 'process_buffer' ) );
    }

    /**
     * Rewrite the page so every non-necessary script and iframe is inert.
     *
     * Nothing here reads the visitor's cookie. Every visitor is served the same
     * document with everything held, and the browser releases whatever their
     * stored choice permits — see unblockScripts() in banner.js.
     *
     * This method used to return the buffer untouched when the cookie said the
     * visitor had accepted everything. On a site with a page cache that was
     * enough to leak consent between people: the first visitor to accept primed
     * the cache with fully unblocked HTML, and everyone served that copy got
     * the trackers running whatever they themselves had chosen. Client-side
     * code could not undo it either, because by then the tags were already in
     * the document and had already fired.
     *
     * Because the output no longer varies by visitor it is safe to cache, and
     * the rewriting cost is paid once per cache miss rather than once per
     * request.
     *
     * @param string $buffer Page HTML.
     * @return string
     */
    public function process_buffer( $buffer ) {
        $buffer = $this->apply_builtin_rules( $buffer );
        $buffer = $this->apply_custom_rules( $buffer );

        return $buffer;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Built-in rules
    // ─────────────────────────────────────────────────────────────────────

    private function apply_builtin_rules( $html ) {
        foreach ( self::$builtin_services as $category => $services ) {
            // Necessary scripts keep the site working and are never withheld.
            if ( 'necessary' === $category ) {
                continue;
            }

            foreach ( $services as $service ) {
                foreach ( $service['domains'] as $domain ) {
                    if ( 'script' === $service['type'] || 'both' === $service['type'] ) {
                        $html = $this->block_script_src( $html, $domain, $category );
                    }
                    if ( 'iframe' === $service['type'] || 'both' === $service['type'] ) {
                        $html = $this->block_iframe_src( $html, $domain, $service['name'], $category );

                        // Optimisers may already have swapped the iframe for a
                        // click-to-play facade before this buffer ran.
                        $html = $this->block_facade( $html, $domain, $service['name'], $category );
                    }
                }
            }
        }

        // Poster images belonging to the embeds held above.
        foreach ( self::$thumbnail_hosts as $host ) {
            $html = $this->block_image_src( $html, $host, 'marketing' );
        }

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Custom (manually-added) rules
    // ─────────────────────────────────────────────────────────────────────

    private function apply_custom_rules( $html ) {
        foreach ( $this->blocked_scripts as $script ) {
            $category = $script['category'] ?? 'marketing';

            if ( 'necessary' === $category ) {
                continue;
            }

            $id   = $script['identifier'];
            $type = $script['type'] ?? 'src';

            if ( 'src' === $type ) {
                $html = $this->block_script_src( $html, $id, $category );
            } elseif ( 'inline' === $type ) {
                $html = $this->block_inline_script( $html, $id, $category );
            } elseif ( 'iframe' === $type ) {
                $html = $this->block_iframe_src( $html, $id, $script['name'] ?? '', $category );
                $html = $this->block_facade( $html, $id, $script['name'] ?? '', $category );
            }
        }

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Blocking primitives
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Block <script src="…"> tags whose src contains $pattern.
     */
    private function block_script_src( $html, $pattern, $category = 'marketing' ) {
        // Now that blocking runs for every visitor rather than only those
        // without consent, this loop is on the critical path for each cache
        // miss. A substring test costs a fraction of a regex pass over the
        // whole document, and the overwhelming majority of the built-in
        // domains are absent from any given page.
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $regex = '/<script(\s[^>]*)?src=(["\'])([^"\']*'
               . preg_quote( $pattern, '/' )
               . '[^"\']*)(\2)/i';

        return preg_replace_callback( $regex, function ( $m ) use ( $category ) {
            $attrs = isset( $m[1] ) ? $m[1] : '';

            // Leave tags this method has already handled alone. The pattern
            // looks for "src=", which also occurs inside the data-mbr-cc-src
            // attribute written below — so without this guard a second pass
            // over the same HTML wraps the tag again, nesting the markers and
            // corrupting the stored source URL.
            if ( false !== strpos( $attrs, 'data-mbr-cc-blocked' ) ) {
                return $m[0];
            }

            $src   = $m[3];
            return '<script' . $attrs
                 . ' type="text/plain"'
                 . ' data-mbr-cc-blocked="true"'
                 . ' data-mbr-cc-category="' . esc_attr( $category ) . '"'
                 . ' data-mbr-cc-src="' . esc_attr( $src ) . '"';
        }, $html ) ?? $html;
    }

    /**
     * Block <script>…content…</script> tags whose body contains $pattern.
     *
     * The body is matched with a tempered dot — (?:(?!<\/script>)[\s\S])* —
     * which matches any character except at a position where </script> begins.
     * The match therefore cannot leave the script it started in.
     *
     * The previous pattern used a lazy .*? with no such boundary. Where an
     * earlier, unrelated <script> appeared first on the page, .*? consumed that
     * script's closing tag and every byte after it — headings, paragraphs,
     * whatever lay between — until it found $pattern in some later script. The
     * callback then replaced that entire span with one tag, so the wrong script
     * was blocked, the real one was deleted, and the page content in between
     * silently vanished.
     *
     * @param string $html     Page buffer.
     * @param string $pattern  Literal string to find in the script body.
     * @param string $category Consent category the script belongs to.
     * @return string
     */
    private function block_inline_script( $html, $pattern, $category = 'marketing' ) {
        // Cheap rejection before any regex runs. As well as skipping the work
        // entirely on pages that cannot match, this keeps the tempered dot away
        // from its worst case: on a miss it would otherwise walk each script
        // body once per starting offset before giving up.
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $escaped = preg_quote( $pattern, '/' );
        $body    = '(?:(?!<\/script>)[\s\S])*';

        $regex = '/<script(\s[^>]*)?>(' . $body . $escaped . $body . ')<\/script>/i';

        return preg_replace_callback(
            $regex,
            function ( $m ) use ( $category ) {
                $attrs = isset( $m[1] ) ? $m[1] : '';

                // Leave a tag alone once it has been blocked. Two custom
                // patterns can both occur in one inline script — say a tag
                // that calls gtag() and fbq() — and without this the second
                // call re-wraps the first's output, duplicating the markers
                // and stripping the type attribute it had just written.
                if ( false !== strpos( $attrs, 'data-mbr-cc-blocked' ) ) {
                    return $m[0];
                }

                // The body is captured directly by the match above. The previous
                // implementation threw the whole match into a second lazy regex
                // to recover it, which was the same bug a second time.
                $content = isset( $m[2] ) ? $m[2] : '';

                // Strip any existing type attribute, then set to text/plain.
                $attrs  = preg_replace( '/\s*type=["\'][^"\']*["\']/', '', $attrs );
                $attrs .= ' type="text/plain" data-mbr-cc-blocked="true"'
                       . ' data-mbr-cc-category="' . esc_attr( $category ) . '"';

                return '<script' . $attrs . '>' . $content . '</script>';
            },
            $html
        ) ?? $html;
    }

    /**
     * Block <iframe src="…"> tags whose src contains $pattern.
     *
     * Replaces the entire <iframe>…</iframe> with:
     *   - The branded placeholder overlay (if enabled), plus
     *   - The original iframe with src removed and hidden (ready to restore
     *     when consent is later granted via banner.js unblockScripts).
     *
     * The regex intentionally handles:
     *   - Single or double quotes around src.
     *   - src appearing anywhere in the tag (not necessarily first).
     *   - Self-closing or paired iframes.
     */
    private function block_iframe_src( $html, $pattern, $service_name = '', $category = 'marketing' ) {
        // Four regex passes per call, one per source attribute, so the early
        // rejection matters more here than anywhere else.
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $placeholder_class = 'MBR_CC_Blocked_Placeholder';
        $escaped           = preg_quote( $pattern, '/' );

        // Build per-attribute regexes. The 'src' pattern uses a negative
        // lookbehind so it doesn't accidentally match 'data-lazy-src'.
        $attr_patterns = array(
            'src'            => '(?<![a-zA-Z0-9_-])src',
            'data-lazy-src'  => 'data-lazy-src',
            'data-src'       => 'data-src',
            'data-rocket-src'=> 'data-rocket-src',
        );

        foreach ( $attr_patterns as $attr => $attr_regex ) {
            $regex = '/<iframe(\s[^>]*?)?' . $attr_regex
                   . '=(["\'])([^"\']*' . $escaped . '[^"\']*)(\2)([^>]*)>/i';

            $html = ( preg_replace_callback(
                $regex,
                function ( $m ) use ( $service_name, $category, $placeholder_class ) {
                    $before = isset( $m[1] ) ? $m[1] : '';
                    $src    = $m[3];
                    $after  = isset( $m[5] ) ? $m[5] : '';

                    $blocked = '<iframe'
                        . $before
                        . ' data-mbr-cc-blocked="true"'
                        . ' data-mbr-cc-category="' . esc_attr( $category ) . '"'
                        . ' data-mbr-cc-src="' . esc_attr( $src ) . '"'
                        . $after
                        . ' style="display:none !important" aria-hidden="true">';

                    // Always render a placeholder — silently hiding content with no
                    // explanation is bad UX and leaves users with no way to unblock it.
                    // The admin toggle controls customisation options, not visibility.
                    $overlay = class_exists( $placeholder_class )
                        ? $placeholder_class::render( array( 'service' => $service_name ) )
                        : '';

                    return $overlay . $blocked;
                },
                $html
            ) ?? $html );
        }

        return $html;
    }

    /**
     * Attributes used by click-to-play video facades to hold the real embed
     * URL until the visitor clicks.
     *
     * @var string[]
     */
    private static $facade_attrs = array(
        'data-src',
        'data-video-src',
        'data-embed-src',
        'data-lazy-src',
        'data-url',
    );

    /**
     * Hosts serving video poster images.
     *
     * A facade avoids the embed's cookies but still fetches its thumbnail, so
     * the visitor's IP address reaches the provider on page load whether or not
     * they ever press play.
     *
     * @var string[]
     */
    private static $thumbnail_hosts = array(
        'i.ytimg.com',
        'img.youtube.com',
        'i.vimeocdn.com',
    );

    /**
     * Block click-to-play video facades.
     *
     * A facade is a performance optimisation: the iframe is replaced with a
     * poster image and a container holding the embed URL in a data attribute,
     * and the real iframe is built in JavaScript when the visitor clicks. Both
     * MBR Performance and several third-party optimisers do this.
     *
     * The consequence for consent is that by the time this class sees the page
     * there is no iframe left to block — the markup is a div — so the embed
     * sailed straight through while the site owner reasonably believed it was
     * being held. Renaming the URL attribute leaves the facade's own script
     * with nothing to build from, and banner.js puts it back on consent.
     *
     * This runs after any optimiser because the blocker's buffer opens on
     * template_redirect at priority 1, making it the outermost buffer and so
     * the last callback to run.
     *
     * @param string $html         Page HTML.
     * @param string $pattern      Domain fragment to match.
     * @param string $service_name Service label for the placeholder.
     * @param string $category     Consent category.
     * @return string
     */
    private function block_facade( $html, $pattern, $service_name = '', $category = 'marketing' ) {
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $placeholder_class = 'MBR_CC_Blocked_Placeholder';
        $escaped           = preg_quote( $pattern, '/' );

        foreach ( self::$facade_attrs as $attr ) {
            // Any element except an iframe — those are handled separately and
            // matching them here would wrap them twice.
            $regex = '/<(?!iframe\b)([a-z][a-z0-9]*)((?:\s[^>]*?)?)\s'
                   . preg_quote( $attr, '/' )
                   . '=(["\'])([^"\']*' . $escaped . '[^"\']*)\3((?:[^>]*?)?)>/i';

            $html = ( preg_replace_callback(
                $regex,
                function ( $m ) use ( $attr, $service_name, $category, $placeholder_class ) {
                    $tag    = $m[1];
                    $before = $m[2];
                    $url    = $m[4];
                    $after  = $m[5];

                    if ( false !== strpos( $before . $after, 'data-mbr-cc-blocked' ) ) {
                        return $m[0];
                    }

                    // The original attribute name travels with the element so
                    // the browser can put the facade back exactly as it was.
                    $blocked = '<' . $tag
                        . $before
                        . ' data-mbr-cc-blocked="true"'
                        . ' data-mbr-cc-facade="true"'
                        . ' data-mbr-cc-attr="' . esc_attr( $attr ) . '"'
                        . ' data-mbr-cc-category="' . esc_attr( $category ) . '"'
                        . ' data-mbr-cc-src="' . esc_attr( $url ) . '"'
                        . ' data-mbr-cc-hidden="true"'
                        . $after
                        . '>';

                    $overlay = class_exists( $placeholder_class )
                        ? $placeholder_class::render( array( 'service' => $service_name ) )
                        : '';

                    return $overlay . $blocked;
                },
                $html
            ) ?? $html );
        }

        return $html;
    }

    /**
     * Block poster images served by video providers.
     *
     * Replaces the source with a transparent pixel so the layout does not
     * collapse, keeping the real URL for the browser to restore on consent.
     *
     * @param string $html     Page HTML.
     * @param string $pattern  Host fragment to match.
     * @param string $category Consent category.
     * @return string
     */
    private function block_image_src( $html, $pattern, $category = 'marketing' ) {
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $transparent = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        $regex = '/<img((?:\s[^>]*?)?)\s(?<![a-zA-Z0-9_-])src=(["\'])([^"\']*'
               . preg_quote( $pattern, '/' ) . '[^"\']*)\2((?:[^>]*?)?)>/i';

        return preg_replace_callback(
            $regex,
            function ( $m ) use ( $category, $transparent ) {
                $before = $m[1];
                $url    = $m[3];
                $after  = $m[4];

                if ( false !== strpos( $before . $after, 'data-mbr-cc-blocked' ) ) {
                    return $m[0];
                }

                return '<img' . $before
                    . ' src="' . $transparent . '"'
                    . ' data-mbr-cc-blocked="true"'
                    . ' data-mbr-cc-image="true"'
                    . ' data-mbr-cc-category="' . esc_attr( $category ) . '"'
                    . ' data-mbr-cc-src="' . esc_attr( $url ) . '"'
                    . $after
                    . '>';
            },
            $html
        ) ?? $html;
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD — custom blocked-script entries (used by Scanner screen / AJAX)
    // ─────────────────────────────────────────────────────────────────────

    public function add_blocked_script( $script ) {
        $defaults = array(
            'name'        => '',
            'identifier'  => '',
            'type'        => 'src',
            'category'    => 'marketing',
            'description' => '',
        );
        $script = wp_parse_args( $script, $defaults );
        if ( empty( $script['name'] ) || empty( $script['identifier'] ) ) {
            return false;
        }
        $this->blocked_scripts[] = $script;
        return update_option( 'mbr_cc_blocked_scripts', $this->blocked_scripts );
    }

    public function remove_blocked_script( $index ) {
        if ( ! isset( $this->blocked_scripts[ $index ] ) ) {
            return false;
        }
        unset( $this->blocked_scripts[ $index ] );
        $this->blocked_scripts = array_values( $this->blocked_scripts );
        return update_option( 'mbr_cc_blocked_scripts', $this->blocked_scripts );
    }

    public function update_blocked_script( $index, $script ) {
        if ( ! isset( $this->blocked_scripts[ $index ] ) ) {
            return false;
        }
        $this->blocked_scripts[ $index ] = array_merge( $this->blocked_scripts[ $index ], $script );
        return update_option( 'mbr_cc_blocked_scripts', $this->blocked_scripts );
    }

    public function get_blocked_scripts() {
        return $this->blocked_scripts;
    }

    public function clear_blocked_scripts() {
        $this->blocked_scripts = array();
        return delete_option( 'mbr_cc_blocked_scripts' );
    }

    /**
     * Return the built-in service list (used by admin UI if needed).
     *
     * @return array
     */
    public static function get_builtin_services() {
        return self::$builtin_services;
    }
}
