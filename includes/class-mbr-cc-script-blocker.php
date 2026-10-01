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

    /**
     * Shared instance.
     *
     * @var MBR_CC_Script_Blocker|null
     */
    private static $instance = null;

    /**
     * Custom blocked-script entries from the database.
     *
     * @var array
     */
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
                // Arrives as <link rel="stylesheet">, never as a script. It was
                // declared 'script' until 2.4.5 and therefore never blocked.
                // Blocking the stylesheet also stops fonts.gstatic.com, which is
                // fetched by the stylesheet rather than by the page.
                'type'    => 'stylesheet',
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

    /**
     * Get the shared instance.
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Set up the hooks.
     */
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

    /**
     * Load the custom blocked-script entries from the database.
     */
    private function load_blocked_scripts() {
        $this->blocked_scripts = get_option( 'mbr_cc_blocked_scripts', array() );
    }

    /**
     * Start buffer.
     */
    public function start_buffer() {
        if ( is_admin() || wp_doing_ajax() ) {
            return;
        }

        // Until 2.3.4 this was the whole test, and the exclusion settings on
        // the Settings screen governed the banner only. Scripts were held back
        // on every front-end page regardless, including pages the owner had
        // explicitly excluded, where nothing was rendered that could release
        // them. See MBR_CC_Enhanced_Customization::should_enforce_consent()
        // for what the two settings now mean.
        if ( class_exists( 'MBR_CC_Enhanced_Customization' )
            && ! MBR_CC_Enhanced_Customization::should_enforce_consent() ) {
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

    /**
     * Block the built-in third-party scripts in the page HTML.
     *
     * @param mixed $html Html.
     */
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
                    if ( 'stylesheet' === $service['type'] || 'both' === $service['type'] ) {
                        $html = $this->block_stylesheet_href( $html, $domain, $category );
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

    /**
     * Block the custom scripts configured by the site owner.
     *
     * @param mixed $html Html.
     */
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
            } elseif ( 'stylesheet' === $type ) {
                $html = $this->block_stylesheet_href( $html, $id, $category );
            } elseif ( 'image' === $type ) {
                // Until 2.4.1 custom rules could only be 'src', 'inline' or
                // 'iframe', all of which rewrite <script> or <iframe>. An <img>
                // matched none of them, so a rule against an image host was
                // accepted, displayed, and could never fire — the Consent
                // Doctor reported the host as escaping a rule that was in fact
                // incapable of holding it.
                //
                // This matters more than it sounds. WordPress core emits
                // Gravatar avatars as plain <img> on any comment thread, which
                // sends the visitor's IP address to a third party before
                // consent, on a huge number of sites. Tracking pixels are the
                // same shape.
                $html = $this->block_image_src( $html, $id, $category );
            }
        }

        return $html;
    }

    // ─────────────────────────────────────────────────────────────────────
    // Tag and attribute handling
    // ─────────────────────────────────────────────────────────────────────
    //
    // Added in 2.6.0. Each primitive used to find its attribute with its own
    // regular expression, and every one of them assumed the attribute was
    // written name="value" with no spaces and a quote. HTML does not require
    // either: <script src = "…"> and <script src=…> are both valid, both are
    // what minifiers and hand-written templates produce, and both passed
    // straight through unblocked. Appending type="text/plain" rather than
    // replacing an existing type was the second fault — browsers keep the
    // FIRST of two identical attributes, so a tag that already said
    // type="text/javascript" stayed executable.
    //
    // The primitives now parse the opening tag into attributes, change the
    // attributes themselves, and write the tag back. Values are carried in
    // their original, still-encoded form, so nothing is decoded and
    // re-encoded differently on the way through.

    /**
     * Script types a browser runs as classic JavaScript. A blocked script
     * with any other type (module, importmap, a JSON data block…) has that
     * type recorded so it is restored as what it was.
     *
     * @var string[]
     */
    private static $classic_script_types = array(
        '', 'text/javascript', 'application/javascript', 'text/ecmascript',
        'application/ecmascript', 'application/x-javascript', 'text/x-javascript',
        'text/jscript', 'text/livescript', 'application/x-ecmascript',
        'text/x-ecmascript', 'text/javascript1.0', 'text/javascript1.1',
        'text/javascript1.2', 'text/javascript1.3', 'text/javascript1.4',
        'text/javascript1.5',
    );

    /**
     * Attributes through which an <img> or <source> can make a request.
     * srcset matters most: WordPress core's get_avatar() emits a 2x srcset,
     * and on a high-density screen the browser fetches that candidate
     * whatever src says. The data-* entries are where lazy-loading plugins
     * park the real URL until their script swaps it in.
     *
     * @var string[]
     */
    private static $image_source_attrs = array(
        'src', 'srcset', 'data-src', 'data-srcset', 'data-lazy-src',
        'data-lazy-srcset', 'data-original', 'data-original-set',
    );

    /**
     * Rewrite every opening tag of the given names through a callback.
     *
     * The callback receives the parsed attribute list and returns a new list,
     * a complete replacement string, or null to leave the tag exactly as it was.
     *
     * @param string   $html      Page HTML.
     * @param string   $names     Tag names as a regex alternation, e.g. 'img|source'.
     * @param string   $needle    Cheap pre-filter: tags not containing it are skipped.
     * @param callable $callback  function( array $attrs, string $tag_name ): ?array.
     * @return string
     */
    private function rewrite_tags( $html, $names, $needle, $callback ) {
        // A quoted value may legitimately contain ">", so the tag body is
        // matched as runs of non-quote characters or whole quoted strings.
        $regex = '/<(' . $names . ')(?=[\s\/>])((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/i';

        return preg_replace_callback(
            $regex,
            function ( $m ) use ( $needle, $callback ) {
                if ( '' !== $needle && false === stripos( $m[2], $needle ) ) {
                    return $m[0];
                }

                $body         = $m[2];
                $self_closing = (bool) preg_match( '/\/\s*$/', $body );
                if ( $self_closing ) {
                    $body = preg_replace( '/\/\s*$/', '', $body );
                }

                $attrs = self::parse_attributes( $body );
                $new   = call_user_func( $callback, $attrs, strtolower( $m[1] ) );

                if ( null === $new ) {
                    return $m[0];
                }

                // A string is a complete replacement — used where markup has
                // to be inserted before the tag, such as a placeholder.
                if ( is_string( $new ) ) {
                    return $new;
                }

                return self::build_tag( $m[1], $new, $self_closing );
            },
            $html
        ) ?? $html;
    }

    /**
     * Parse the attribute part of an opening tag.
     *
     * Handles double-quoted, single-quoted, unquoted and valueless attributes,
     * with or without whitespace around "=". Returns a list of
     * array( name, raw_value|null ) in document order; raw values are exactly
     * as written, entities included.
     *
     * @param string $body Everything between the tag name and ">".
     * @return array[]
     */
    private static function parse_attributes( $body ) {
        $attrs = array();

        preg_match_all(
            '/([^\s"\'>\/=]+)(?:\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\'=<>`]+)))?/',
            $body,
            $matches,
            PREG_SET_ORDER
        );

        foreach ( $matches as $m ) {
            $value = null;
            if ( isset( $m[2] ) && '' !== $m[2] ) {
                $value = $m[2];
            } elseif ( isset( $m[3] ) && '' !== $m[3] ) {
                $value = $m[3];
            } elseif ( isset( $m[4] ) && '' !== $m[4] ) {
                $value = $m[4];
            } elseif ( preg_match( '/=\s*(""|\'\')$/', $m[0] ) ) {
                $value = '';
            }

            $attrs[] = array( $m[1], $value );
        }

        return $attrs;
    }

    /**
     * Write a tag back from its attribute list.
     *
     * @param string  $name         Tag name as it appeared.
     * @param array[] $attrs        array( name, raw_value|null ) pairs.
     * @param bool    $self_closing Whether to keep a trailing slash.
     * @return string
     */
    private static function build_tag( $name, $attrs, $self_closing = false ) {
        $out = '<' . $name;

        foreach ( $attrs as $attr ) {
            $out .= ' ' . $attr[0];
            if ( null !== $attr[1] ) {
                // Raw values from double quotes or unquoted positions cannot
                // contain '"'; values from single quotes can, and are the only
                // thing this changes.
                $out .= '="' . str_replace( '"', '&quot;', $attr[1] ) . '"';
            }
        }

        return $out . ( $self_closing ? ' />' : '>' );
    }

    /**
     * First value of an attribute, as the browser would see it, or null.
     * HTML keeps the first of duplicate attributes and ignores the rest.
     *
     * @param mixed $attrs Attrs.
     * @param mixed $name Name.
     */
    private static function attr_get( $attrs, $name ) {
        foreach ( $attrs as $attr ) {
            if ( 0 === strcasecmp( $attr[0], $name ) ) {
                return null === $attr[1] ? '' : $attr[1];
            }
        }
        return null;
    }

    /**
     * Remove every occurrence of the named attributes.
     *
     * @param mixed $attrs Attrs.
     * @param mixed $names Names.
     */
    private static function attr_remove( $attrs, $names ) {
        $names = array_map( 'strtolower', (array) $names );
        return array_values( array_filter( $attrs, function ( $attr ) use ( $names ) {
            return ! in_array( strtolower( $attr[0] ), $names, true );
        } ) );
    }

    /**
     * Does a raw attribute value contain the pattern, encoded or decoded?
     *
     * @param mixed $raw Raw.
     * @param mixed $pattern Pattern.
     */
    private static function value_contains( $raw, $pattern ) {
        if ( null === $raw || '' === $pattern ) {
            return false;
        }
        return false !== stripos( $raw, $pattern )
            || false !== stripos( html_entity_decode( $raw, ENT_QUOTES, 'UTF-8' ), $pattern );
    }

    /**
     * Record a script's type if restoring it as classic JavaScript would be
     * wrong. Returns the attributes to add.
     *
     * @param mixed $type Type.
     */
    private static function type_marker( $type ) {
        if ( null === $type ) {
            return array();
        }
        $normalised = strtolower( trim( html_entity_decode( $type, ENT_QUOTES, 'UTF-8' ) ) );
        if ( in_array( $normalised, self::$classic_script_types, true ) ) {
            return array();
        }
        return array( array( 'data-mbr-cc-type', esc_attr( $normalised ) ) );
    }

    // ─────────────────────────────────────────────────────────────────────
    // Blocking primitives
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Block <script src="…"> tags whose src contains $pattern.
     *
     * @param mixed  $html     HTML to process.
     * @param mixed  $pattern  Pattern.
     * @param string $category Category slug.
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

        return $this->rewrite_tags( $html, 'script', $pattern, function ( $attrs ) use ( $pattern, $category ) {
            // Already handled by an earlier rule on this pass.
            if ( null !== self::attr_get( $attrs, 'data-mbr-cc-blocked' ) ) {
                return null;
            }

            $src = self::attr_get( $attrs, 'src' );
            if ( null === $src || ! self::value_contains( $src, $pattern ) ) {
                return null;
            }

            $type  = self::attr_get( $attrs, 'type' );
            $attrs = self::attr_remove( $attrs, array( 'src', 'type' ) );

            // Exactly one type, and it is inert. With src removed as well, a
            // script body — some tags carry configuration inside the element —
            // has nothing to execute as either.
            array_unshift( $attrs, array( 'type', 'text/plain' ) );

            return array_merge(
                $attrs,
                self::type_marker( $type ),
                array(
                    array( 'data-mbr-cc-blocked', 'true' ),
                    array( 'data-mbr-cc-category', esc_attr( $category ) ),
                    array( 'data-mbr-cc-src', $src ),
                )
            );
        } );
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

        // Attributes are matched as runs of non-quote characters or whole
        // quoted strings, so a ">" inside an attribute value cannot end the tag.
        $regex = '/<script((?:\s(?:[^>"\']|"[^"]*"|\'[^\']*\')*)?)>(' . $body . $escaped . $body . ')<\/script>/i';

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

                // Replace every type attribute with exactly one inert one. The
                // old expression only recognised type="…" with no spaces, so
                // type = "module" or an unquoted type survived beside the new
                // one — and browsers honour the first.
                $parsed = self::parse_attributes( $attrs );
                $type   = self::attr_get( $parsed, 'type' );
                $parsed = self::attr_remove( $parsed, array( 'type' ) );
                array_unshift( $parsed, array( 'type', 'text/plain' ) );
                $parsed = array_merge(
                    $parsed,
                    self::type_marker( $type ),
                    array(
                        array( 'data-mbr-cc-blocked', 'true' ),
                        array( 'data-mbr-cc-category', esc_attr( $category ) ),
                    )
                );

                return self::build_tag( 'script', $parsed ) . $content . '</script>';
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
     *
     * @param mixed  $html         HTML to process.
     * @param mixed  $pattern      Pattern.
     * @param string $service_name Service name.
     * @param string $category     Category slug.
     */
    private function block_iframe_src( $html, $pattern, $service_name = '', $category = 'marketing' ) {
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $placeholder_class = 'MBR_CC_Blocked_Placeholder';

        // Attributes an iframe's real URL may sit in: its own src, or the
        // data attribute a lazy-loader moves it to.
        $source_attrs = array( 'src', 'data-lazy-src', 'data-src', 'data-rocket-src' );

        return $this->rewrite_tags( $html, 'iframe', $pattern, function ( $attrs ) use ( $pattern, $service_name, $category, $placeholder_class, $source_attrs ) {
            if ( null !== self::attr_get( $attrs, 'data-mbr-cc-blocked' ) ) {
                return null;
            }

            $url = null;
            foreach ( $source_attrs as $name ) {
                $value = self::attr_get( $attrs, $name );
                if ( self::value_contains( $value, $pattern ) ) {
                    $url = $value;
                    break;
                }
            }
            if ( null === $url ) {
                return null;
            }

            // An existing style attribute is kept aside and restored on
            // consent. Appending a second one, as before 2.6.0, did nothing:
            // the browser uses the first, so an iframe that already had a
            // style was never hidden.
            $style = self::attr_get( $attrs, 'style' );
            $attrs = self::attr_remove( $attrs, array_merge( $source_attrs, array( 'style', 'aria-hidden' ) ) );

            $attrs = array_merge( $attrs, array(
                array( 'data-mbr-cc-blocked', 'true' ),
                array( 'data-mbr-cc-category', esc_attr( $category ) ),
                array( 'data-mbr-cc-src', $url ),
            ) );
            if ( null !== $style && '' !== $style ) {
                $attrs[] = array( 'data-mbr-cc-style', $style );
            }
            $attrs[] = array( 'style', 'display:none !important' );
            $attrs[] = array( 'aria-hidden', 'true' );

            // Always render a placeholder — silently hiding content with no
            // explanation is bad UX and leaves users with no way to unblock it.
            // The admin toggle controls customisation options, not visibility.
            $overlay = class_exists( $placeholder_class )
                ? $placeholder_class::render( array( 'service' => $service_name ) )
                : '';

            return $overlay . self::build_tag( 'iframe', $attrs );
        } );
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
        $facade_attrs      = self::$facade_attrs;

        // Any container element. Iframes, images, sources, scripts and links
        // have their own handlers: matching an iframe here would wrap it twice,
        // and a lazy-loaded thumbnail with data-src="…img.youtube.com…" would
        // otherwise be mistaken for a video facade and restored as an embed.
        return $this->rewrite_tags( $html, '(?!(?:iframe|img|source|script|link)\b)[a-z][a-z0-9-]*', $pattern, function ( $attrs, $tag ) use ( $pattern, $service_name, $category, $placeholder_class, $facade_attrs ) {
            if ( null !== self::attr_get( $attrs, 'data-mbr-cc-blocked' ) ) {
                return null;
            }

            foreach ( $facade_attrs as $attr ) {
                $url = self::attr_get( $attrs, $attr );
                if ( ! self::value_contains( $url, $pattern ) ) {
                    continue;
                }

                // The original attribute name travels with the element so the
                // browser can put the facade back exactly as it was.
                $attrs = array_merge(
                    self::attr_remove( $attrs, array( $attr ) ),
                    array(
                        array( 'data-mbr-cc-blocked', 'true' ),
                        array( 'data-mbr-cc-facade', 'true' ),
                        array( 'data-mbr-cc-attr', esc_attr( $attr ) ),
                        array( 'data-mbr-cc-category', esc_attr( $category ) ),
                        array( 'data-mbr-cc-src', $url ),
                        array( 'data-mbr-cc-hidden', 'true' ),
                    )
                );

                $overlay = class_exists( $placeholder_class )
                    ? $placeholder_class::render( array( 'service' => $service_name ) )
                    : '';

                return $overlay . self::build_tag( $tag, $attrs );
            }

            return null;
        } );
    }

    /**
     * Block <link rel="stylesheet" href="…"> whose href contains $pattern.
     *
     * There was no stylesheet handling at all before 2.4.5, which meant the
     * built-in Google Fonts rule — declared as type 'script' — could never fire.
     * Google Fonts arrives as a stylesheet link, so the rule was listed, shown
     * in the UI, and matched nothing. Loading it discloses the visitor's IP
     * address to Google before consent, which a German court has held unlawful
     * under the GDPR, so this was not a cosmetic gap.
     *
     * The href is moved to a data attribute rather than pointed somewhere
     * harmless. A <link rel="stylesheet"> with no href fetches nothing, whereas
     * media="not all" and similar tricks still fetch at low priority in most
     * browsers.
     *
     * @param string $html     Page HTML.
     * @param string $pattern  Host fragment to match.
     * @param string $category Consent category.
     * @return string
     */
    private function block_stylesheet_href( $html, $pattern, $category = 'preferences' ) {
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        return $this->rewrite_tags( $html, 'link', $pattern, function ( $attrs ) use ( $pattern, $category ) {
            if ( null !== self::attr_get( $attrs, 'data-mbr-cc-blocked' ) ) {
                return null;
            }

            $href = self::attr_get( $attrs, 'href' );
            if ( null === $href || ! self::value_contains( $href, $pattern ) ) {
                return null;
            }

            // Only links that fetch or connect. A <link rel="canonical"> or an
            // alternate carrying a matching host must be left alone.
            $rel = strtolower( (string) self::attr_get( $attrs, 'rel' ) );
            if ( ! preg_match( '/(^|\s)(stylesheet|preload|modulepreload|prefetch|preconnect|dns-prefetch)(\s|$)/', $rel ) ) {
                return null;
            }

            return array_merge(
                self::attr_remove( $attrs, array( 'href' ) ),
                array(
                    array( 'data-mbr-cc-blocked', 'true' ),
                    array( 'data-mbr-cc-stylesheet', 'true' ),
                    array( 'data-mbr-cc-category', esc_attr( $category ) ),
                    array( 'data-mbr-cc-href', $href ),
                )
            );
        } );
    }

    /**
     * Hold images — and every alternative source a browser could choose.
     *
     * CHANGED IN 2.6.0. Only src used to be replaced. srcset stayed live, and
     * a browser chooses from srcset independently of src — on a high-density
     * screen it fetches the 2x candidate and never looks at src at all.
     * WordPress core's get_avatar() writes exactly that srcset, so the
     * Gravatar hold added in 2.4.8 still leaked the visitor's IP address to
     * Automattic on most phones and many laptops. <picture><source srcset> and
     * <video><source src> are separate elements the old code never looked at,
     * and lazy-loading plugins keep the real URL in data-src until their own
     * script swaps it in, which would happen before consent.
     *
     * Now every source attribute on a matching <img> or <source> is moved to
     * data-mbr-cc-held-{name}, and banner.js puts each one back. An <img> gets
     * a transparent placeholder src so the layout does not move.
     *
     * @param mixed  $html     HTML to process.
     * @param mixed  $pattern  Pattern.
     * @param string $category Category slug.
     */
    private function block_image_src( $html, $pattern, $category = 'marketing' ) {
        if ( '' === $pattern || stripos( $html, $pattern ) === false ) {
            return $html;
        }

        $transparent = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

        return $this->rewrite_tags( $html, 'img|source', $pattern, function ( $attrs, $tag ) use ( $pattern, $category, $transparent ) {
            if ( null !== self::attr_get( $attrs, 'data-mbr-cc-blocked' ) ) {
                return null;
            }

            // Does any source the browser might use point at the host?
            $matched = false;
            foreach ( self::$image_source_attrs as $name ) {
                if ( self::value_contains( self::attr_get( $attrs, $name ), $pattern ) ) {
                    $matched = true;
                    break;
                }
            }
            if ( ! $matched ) {
                return null;
            }

            // Hold every source, not just the matching one: a srcset mixing
            // hosts would otherwise still resolve to the tracked candidate.
            $held = array();
            foreach ( self::$image_source_attrs as $name ) {
                $value = self::attr_get( $attrs, $name );
                if ( null !== $value ) {
                    $held[] = array( 'data-mbr-cc-held-' . $name, $value );
                }
            }

            $attrs = self::attr_remove( $attrs, self::$image_source_attrs );

            if ( 'img' === $tag ) {
                array_unshift( $attrs, array( 'src', $transparent ) );
            }

            return array_merge(
                $attrs,
                array(
                    array( 'data-mbr-cc-blocked', 'true' ),
                    array( 'data-mbr-cc-image', 'true' ),
                    array( 'data-mbr-cc-category', esc_attr( $category ) ),
                ),
                $held
            );
        } );
    }

    // ─────────────────────────────────────────────────────────────────────
    // CRUD — custom blocked-script entries (used by Scanner screen / AJAX)
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Add a custom blocked script.
     *
     * @param mixed $script Script.
     */
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

    /**
     * Remove blocked script.
     *
     * @param mixed $index Zero-based index.
     */
    public function remove_blocked_script( $index ) {
        if ( ! isset( $this->blocked_scripts[ $index ] ) ) {
            return false;
        }
        unset( $this->blocked_scripts[ $index ] );
        $this->blocked_scripts = array_values( $this->blocked_scripts );
        return update_option( 'mbr_cc_blocked_scripts', $this->blocked_scripts );
    }

    /**
     * Update blocked script.
     *
     * @param mixed $index Zero-based index.
     * @param mixed $script Script definition.
     */
    public function update_blocked_script( $index, $script ) {
        if ( ! isset( $this->blocked_scripts[ $index ] ) ) {
            return false;
        }
        $this->blocked_scripts[ $index ] = array_merge( $this->blocked_scripts[ $index ], $script );
        return update_option( 'mbr_cc_blocked_scripts', $this->blocked_scripts );
    }

    /**
     * Get blocked scripts.
     */
    public function get_blocked_scripts() {
        return $this->blocked_scripts;
    }

    /**
     * Clear blocked scripts.
     */
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
