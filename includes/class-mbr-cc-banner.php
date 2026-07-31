<?php
/**
 * Cookie Consent Banner display.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Banner class.
 */
class MBR_CC_Banner {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Banner
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Banner
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
        add_action('wp_footer', array($this, 'render_banner'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
    }
    
    /**
     * Enqueue banner assets.
     */
    public function enqueue_assets() {
        if (is_admin()) {
            return;
        }
        
        wp_enqueue_style(
            'mbr-cc-banner',
            MBR_CC_PLUGIN_URL . 'assets/css/banner.css',
            array(),
            MBR_CC_VERSION
        );
        
        wp_enqueue_script(
            'mbr-cc-banner',
            MBR_CC_PLUGIN_URL . 'assets/js/banner.js',
            array('jquery'),
            MBR_CC_VERSION,
            true
        );
        
        // Pass settings to JavaScript.
        wp_localize_script('mbr-cc-banner', 'mbrCcBanner', array(
            'categories' => MBR_CC_Consent_Manager::get_instance()->get_categories(),
            'revisitEnabled' => (bool) get_option('mbr_cc_revisit_consent_enabled', true),
            'revisitText' => get_option('mbr_cc_revisit_consent_text', 'Cookie Settings'),
        ));
        
        // Blocked content overlay assets.
        //
        // These load whenever the class is present, not only when the overlay
        // is switched on. The blocker always renders a placeholder — silently
        // hiding an embed with no explanation is poor UX — so gating the
        // stylesheet on the toggle meant sites with the default settings got
        // the markup with none of the CSS, and visitors saw a heap of unstyled
        // text where a video should be. The toggle governs the overlay's
        // appearance and wording, not whether it exists.
        if ( class_exists( 'MBR_CC_Blocked_Placeholder' ) ) {
            wp_enqueue_style(
                'mbr-cc-blocked-content',
                MBR_CC_PLUGIN_URL . 'assets/css/blocked-content.css',
                array( 'mbr-cc-banner' ),
                MBR_CC_VERSION
            );
            wp_enqueue_script(
                'mbr-cc-blocked-content',
                MBR_CC_PLUGIN_URL . 'assets/js/blocked-content.js',
                array( 'jquery', 'mbr-cc-banner' ),
                MBR_CC_VERSION,
                true
            );
        }
        
        // Elementor video blocker — runs when Elementor is active on the page.
        // Enqueued in the <head> (in_footer=false) with high priority so it
        // executes BEFORE Elementor's own frontend script initialises widgets.
        if ( defined( 'ELEMENTOR_VERSION' ) || class_exists( '\Elementor\Plugin' ) ) {
            wp_enqueue_script(
                'mbr-cc-elementor-video-blocker',
                MBR_CC_PLUGIN_URL . 'assets/js/elementor-video-blocker.js',
                array(), // No jQuery dependency — pure JS runs earlier.
                MBR_CC_VERSION,
                false    // Load in <head> so it runs before Elementor's DOMContentLoaded.
            );
        }

        // Add inline CSS for customization.
        $this->add_inline_styles();
    }
    
    /**
     * Add inline CSS for custom colors.
     */
    private function add_inline_styles() {
        wp_add_inline_style('mbr-cc-banner', self::build_custom_css());
    }
    
    /**
     * Convert a hex colour to an "r, g, b" triplet.
     *
     * @param string $hex   Hex colour, 3 or 6 digit, with or without hash.
     * @param string $fallback Used when $hex cannot be parsed.
     * @return string Comma-separated RGB channels.
     */
    public static function hex_to_rgb($hex, $fallback = '0, 115, 170') {
        $hex = ltrim(trim((string) $hex), '#');
        
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        
        if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return $fallback;
        }
        
        return hexdec(substr($hex, 0, 2)) . ', '
             . hexdec(substr($hex, 2, 2)) . ', '
             . hexdec(substr($hex, 4, 2));
    }
    
    /**
     * Build the banner's generated CSS.
     *
     * Reads options directly so that the preview can short-circuit them with
     * `pre_option_` filters and get byte-identical output to the front end.
     * That is deliberate: a preview that renders through a second code path
     * drifts from reality the moment either path changes.
     *
     * @return string CSS.
     */
    public static function build_custom_css() {
        $primary_color = get_option('mbr_cc_primary_color', '#0073aa');
        $accept_color = get_option('mbr_cc_accept_button_color', '#00a32a');
        $reject_color = get_option('mbr_cc_reject_button_color', '#d63638');
        $text_color = get_option('mbr_cc_text_color', '#ffffff');
        $revisit_text_color = get_option('mbr_cc_revisit_button_text_color', '#000000');
        
        $glass = (bool) get_option('mbr_cc_banner_glassmorphism', false);
        $dark_mode = get_option('mbr_cc_banner_dark_mode', 'off');
        
        if (!in_array($dark_mode, array('off', 'on', 'auto'), true)) {
            $dark_mode = 'off';
        }
        
        $custom_css = "
            /* ── Banner ───────────────────────────────────────────── */
            .mbr-cc-banner {
                background-color: {$primary_color} !important;
                color: {$text_color} !important;
            }

            /* Accept buttons — banner and modal footer */
            .mbr-cc-banner .mbr-cc-btn-accept,
            .mbr-cc-banner .mbr-cc-btn-accept:hover,
            .mbr-cc-banner .mbr-cc-btn-accept:focus,
            .mbr-cc-modal .mbr-cc-btn-accept,
            .mbr-cc-modal .mbr-cc-btn-accept:hover,
            .mbr-cc-modal .mbr-cc-btn-accept:focus {
                background-color: {$accept_color} !important;
                background: {$accept_color} !important;
                color: #ffffff !important;
            }

            /* Reject buttons — banner and modal footer */
            .mbr-cc-banner .mbr-cc-btn-reject,
            .mbr-cc-banner .mbr-cc-btn-reject:hover,
            .mbr-cc-banner .mbr-cc-btn-reject:focus,
            .mbr-cc-modal .mbr-cc-btn-reject,
            .mbr-cc-modal .mbr-cc-btn-reject:hover,
            .mbr-cc-modal .mbr-cc-btn-reject:focus {
                background-color: {$reject_color} !important;
                background: {$reject_color} !important;
                color: #ffffff !important;
            }

            /* Customise button on banner */
            .mbr-cc-banner .mbr-cc-btn-customize {
                border-color: {$text_color} !important;
                color: {$text_color} !important;
            }

            /* Banner close X — inherits banner text colour */
            .mbr-cc-banner .mbr-cc-close {
                color: {$text_color} !important;
            }

            /* ── Revisit button ───────────────────────────────────── */
            .mbr-cc-revisit-consent {
                background-color: {$primary_color} !important;
                color: {$revisit_text_color} !important;
            }
            .mbr-cc-revisit-consent span {
                color: {$revisit_text_color} !important;
            }
            .mbr-cc-revisit-consent svg {
                stroke: {$revisit_text_color} !important;
            }
        ";
        
        if ($glass) {
            $custom_css .= self::build_glass_css($primary_color, $text_color);
        }
        
        if ($dark_mode !== 'off') {
            $dark_css = self::build_dark_css($glass);
            
            $custom_css .= ($dark_mode === 'auto')
                ? "\n@media (prefers-color-scheme: dark) {\n{$dark_css}\n}\n"
                : "\n{$dark_css}\n";
        }
        
        /**
         * Filter the banner's generated CSS.
         *
         * @since 2.3.2
         *
         * @param string $custom_css Generated CSS.
         */
        return apply_filters('mbr_cc_banner_css', $custom_css);
    }
    
    /**
     * Glassmorphism layer.
     *
     * Opacity and blur are set by the site owner. Rather than imposing a floor,
     * the settings screen computes the real WCAG contrast ratio for the chosen
     * combination — compositing the banner colour at that opacity over both a
     * white and a black backdrop, and reporting the worse of the two. If that
     * passes, the banner is legible over any page behind it. That is a stronger
     * guarantee than a fixed opacity limit, which knows nothing about the
     * colours actually in use.
     *
     * Browsers without backdrop-filter, and visitors who have asked for reduced
     * transparency, get the original solid surface.
     *
     * @param string $primary_color Banner background colour.
     * @param string $text_color    Banner text colour.
     * @return string CSS.
     */
    private static function build_glass_css($primary_color, $text_color) {
        $rgb = self::hex_to_rgb($primary_color);
        
        // Admin-controlled, with the filters kept as a developer override.
        $opacity = (int) get_option('mbr_cc_glass_opacity', 82) / 100;
        
        /**
         * Filter the glassmorphism surface opacity.
         *
         * @since 2.3.2
         *
         * @param float $opacity Surface opacity, 0-1.
         */
        $opacity = (float) apply_filters('mbr_cc_glass_opacity', $opacity);
        
        // Absolute bounds only. A fully transparent banner is not a banner, and
        // above 1 is meaningless; between those two the site owner decides, with
        // the contrast readout in the admin telling them what they are choosing.
        $opacity = max(0.1, min(1.0, $opacity));
        $opacity = round($opacity, 2);
        
        $blur = (int) get_option('mbr_cc_glass_blur', 14);
        
        /**
         * Filter the glassmorphism blur radius in pixels.
         *
         * @since 2.3.2
         *
         * @param int $blur Blur radius in px.
         */
        $blur = (int) apply_filters('mbr_cc_glass_blur', $blur);
        $blur = max(0, min(40, $blur));
        
        $text_rgb = self::hex_to_rgb($text_color, '255, 255, 255');
        
        return "
            /* ── Glassmorphism ────────────────────────────────────── */
            /* Banner only. The preference centre is a dense, scrollable panel
               of category toggles and body copy — translucency fights the
               reading rather than decorating it, so the modal stays solid. */
            @supports (backdrop-filter: blur(1px)) or (-webkit-backdrop-filter: blur(1px)) {
                .mbr-cc-banner {
                    background-color: rgba({$rgb}, {$opacity}) !important;
                    -webkit-backdrop-filter: blur({$blur}px) saturate(140%);
                    backdrop-filter: blur({$blur}px) saturate(140%);
                    border: 1px solid rgba({$text_rgb}, 0.18) !important;
                    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.18) !important;
                }
                
                .mbr-cc-banner .mbr-cc-btn-customize {
                    background-color: rgba({$text_rgb}, 0.08) !important;
                }
            }
            
            /* Visitors who have asked for less transparency get the solid surface. */
            @media (prefers-reduced-transparency: reduce) {
                .mbr-cc-banner {
                    background-color: {$primary_color} !important;
                    -webkit-backdrop-filter: none;
                    backdrop-filter: none;
                }
            }
        ";
    }
    
    /**
     * Dark mode layer.
     *
     * Only the banner surface and its text are swapped. The accept and reject
     * button colours are left alone, because those are brand decisions the site
     * owner made deliberately and are usually saturated enough to hold up on a
     * dark surface. A neutral dark grey is used rather than a tinted one so it
     * sits acceptably against any theme.
     *
     * @param bool $glass Whether glassmorphism is also enabled.
     * @return string CSS.
     */
    private static function build_dark_css($glass = false) {
        /**
         * Filter the dark mode surface and text colours.
         *
         * @since 2.3.2
         *
         * @param array $palette Keys: surface, text, muted, border.
         */
        $palette = apply_filters('mbr_cc_dark_palette', array(
            'surface' => '#17191f',
            'text'    => '#e8eaed',
            'muted'   => '#b6bac4',
            'border'  => '#2c2f38',
        ));
        
        $surface = isset($palette['surface']) ? $palette['surface'] : '#17191f';
        $text    = isset($palette['text']) ? $palette['text'] : '#e8eaed';
        $muted   = isset($palette['muted']) ? $palette['muted'] : '#b6bac4';
        $border  = isset($palette['border']) ? $palette['border'] : '#2c2f38';
        
        $surface_rule = $glass
            ? 'background-color: rgba(' . self::hex_to_rgb($surface, '23, 25, 31') . ', 0.86) !important;'
            : "background-color: {$surface} !important;";
        
        return "
            /* ── Dark mode ────────────────────────────────────────── */
            .mbr-cc-banner {
                {$surface_rule}
                color: {$text} !important;
                border-color: {$border} !important;
            }
            
            /* The modal is always a solid surface, even with glass enabled. */
            .mbr-cc-modal__content {
                background-color: {$surface} !important;
                color: {$text} !important;
                border-color: {$border} !important;
            }
            
            .mbr-cc-banner h2,
            .mbr-cc-banner p,
            .mbr-cc-modal__header h3,
            .mbr-cc-modal__body {
                color: {$text} !important;
            }
            
            .mbr-cc-banner .mbr-cc-btn-customize {
                border-color: {$muted} !important;
                color: {$text} !important;
            }
            
            .mbr-cc-banner .mbr-cc-close,
            .mbr-cc-modal__close {
                color: {$text} !important;
            }
            
            .mbr-cc-category {
                border-color: {$border} !important;
            }
            
            .mbr-cc-ccpa-link,
            .mbr-cc-banner a {
                color: {$text} !important;
            }
            
            .mbr-cc-modal__header,
            .mbr-cc-modal__footer {
                border-color: {$border} !important;
            }
        ";
    }
    
    /**
     * Render the consent banner.
     */
    public function render_banner($preview = false) {
        // Don't show on admin pages. The preview renders through this same
        // method on purpose — a second rendering path would drift.
        if (!$preview && is_admin()) {
            return;
        }
        
        // Check if banner should be shown on this page.
        if (!$preview && !MBR_CC_Enhanced_Customization::should_show_banner()) {
            return;
        }
        
        // Get i18n instance for translations.
        $i18n = MBR_CC_I18n_Accessibility::get_instance();
        
        $position = get_option('mbr_cc_banner_position', 'bottom');
        $layout = get_option('mbr_cc_banner_layout', 'bar');
        
        $heading = $i18n::get_translated_string('banner_heading', 
            get_option('mbr_cc_banner_heading', 'We value your privacy'));
        $description = $i18n::get_translated_string('banner_description',
            get_option('mbr_cc_banner_description', 'We use cookies to enhance your browsing experience, serve personalized content, and analyze our traffic. By clicking "Accept All", you consent to our use of cookies.'));
        
        $accept_text = $i18n::get_translated_string('accept_all',
            get_option('mbr_cc_accept_button_text', 'Accept All'));
        $reject_text = $i18n::get_translated_string('reject_all',
            get_option('mbr_cc_reject_button_text', 'Reject All'));
        $customize_text = $i18n::get_translated_string('customize',
            get_option('mbr_cc_customize_button_text', 'Customize'));
        
        // Get base configuration from options
        $base_config = array(
            'show_reject_button' => get_option('mbr_cc_show_reject_button', true),
            'show_customize_button' => get_option('mbr_cc_show_customize_button', true),
            'enable_ccpa' => get_option('mbr_cc_enable_ccpa', false),
        );
        
        // Apply geolocation-based regional overrides
        $config = apply_filters('mbr_cc_banner_config', $base_config);
        
        // Use filtered values
        $show_reject = $config['show_reject_button'];
        $show_customize = $config['show_customize_button'];
        $enable_ccpa = $config['enable_ccpa'];
        
        $ccpa_text = $i18n::get_translated_string('ccpa_link_text',
            get_option('mbr_cc_ccpa_link_text', 'Do Not Sell or Share My Personal Information'));
        
        $classes = array('mbr-cc-banner', 'mbr-cc-banner--' . $position, 'mbr-cc-banner--' . $layout);
        ?>
        
        <!-- Popup Overlay (for popup layout) -->
        <?php if ($layout === 'popup') : ?>
            <div id="mbr-cc-popup-overlay" class="mbr-cc-popup-overlay" style="display: none;" aria-hidden="true"></div>
        <?php endif; ?>
        
        <!-- Cookie Consent Banner -->
        <div id="mbr-cc-banner" 
             class="<?php echo esc_attr(implode(' ', $classes)); ?>" 
             style="display: none;"
             role="dialog"
             aria-labelledby="mbr-cc-banner-heading"
             aria-describedby="mbr-cc-banner-description"
             aria-modal="true">
            <?php if (get_option('mbr_cc_show_close_button', false)) : ?>
                <button type="button" 
                        class="mbr-cc-close" 
                        id="mbr-cc-close" 
                        aria-label="<?php esc_attr_e('Close', 'mbr-cookie-consent'); ?>">×</button>
            <?php endif; ?>
            
            <div class="mbr-cc-banner__container">
                <?php if (get_option('mbr_cc_banner_logo_url')) : ?>
                    <div class="mbr-cc-banner__logo" role="img" aria-label="<?php esc_attr_e('Company logo', 'mbr-cookie-consent'); ?>">
                        <img src="<?php echo esc_url(get_option('mbr_cc_banner_logo_url')); ?>" alt="">
                    </div>
                <?php endif; ?>
                
                <div class="mbr-cc-banner__content">
                    <h3 id="mbr-cc-banner-heading" class="mbr-cc-banner__heading" data-mbr-cc-i18n="banner_heading"><?php echo esc_html($heading); ?></h3>
                    <p id="mbr-cc-banner-description" class="mbr-cc-banner__description" data-mbr-cc-i18n="banner_description"><?php echo esc_html($description); ?></p>
                    
                    <?php if (get_option('mbr_cc_show_privacy_policy_link', false) || get_option('mbr_cc_show_cookie_policy_link', false)) : ?>
                        <p class="mbr-cc-banner__policy-links">
                            <?php if (get_option('mbr_cc_show_privacy_policy_link', false) && get_option('mbr_cc_privacy_policy_url')) : ?>
                                <a href="<?php echo esc_url(get_option('mbr_cc_privacy_policy_url')); ?>" target="_blank" rel="noopener noreferrer" data-mbr-cc-i18n="privacy_policy_text">
                                    <?php echo esc_html(get_option('mbr_cc_privacy_policy_text', 'Privacy Policy')); ?>
                                </a>
                            <?php endif; ?>
                            
                            <?php if (get_option('mbr_cc_show_privacy_policy_link', false) && get_option('mbr_cc_show_cookie_policy_link', false) && get_option('mbr_cc_privacy_policy_url') && get_option('mbr_cc_cookie_policy_url')) : ?>
                                <span class="mbr-cc-separator"> | </span>
                            <?php endif; ?>
                            
                            <?php if (get_option('mbr_cc_show_cookie_policy_link', false) && get_option('mbr_cc_cookie_policy_url')) : ?>
                                <a href="<?php echo esc_url(get_option('mbr_cc_cookie_policy_url')); ?>" target="_blank" rel="noopener noreferrer" data-mbr-cc-i18n="cookie_policy_text">
                                    <?php echo esc_html(get_option('mbr_cc_cookie_policy_text', 'Cookie Policy')); ?>
                                </a>
                            <?php endif; ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($enable_ccpa) : ?>
                        <p class="mbr-cc-banner__ccpa">
                            <button type="button" class="mbr-cc-ccpa-link" id="mbr-cc-ccpa-optout" data-mbr-cc-i18n="ccpa_link_text">
                                <?php echo esc_html($ccpa_text); ?>
                            </button>
                        </p>
                    <?php endif; ?>
                </div>
                
                <div class="mbr-cc-banner__buttons">
                    <button type="button" class="mbr-cc-btn mbr-cc-btn-accept" id="mbr-cc-accept-all" data-mbr-cc-i18n="accept_all">
                        <?php echo esc_html($accept_text); ?>
                    </button>
                    
                    <?php if ($show_reject) : ?>
                        <button type="button" class="mbr-cc-btn mbr-cc-btn-reject" id="mbr-cc-reject-all" data-mbr-cc-i18n="reject_all">
                            <?php echo esc_html($reject_text); ?>
                        </button>
                    <?php endif; ?>
                    
                    <?php if ($show_customize) : ?>
                        <button type="button" class="mbr-cc-btn mbr-cc-btn-customize" id="mbr-cc-customize" data-mbr-cc-i18n="customize">
                            <?php echo esc_html($customize_text); ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Preference Center Modal -->
        <div id="mbr-cc-modal" 
             class="mbr-cc-modal" 
             style="display: none;"
             role="dialog"
             aria-labelledby="mbr-cc-modal-heading"
             aria-modal="true">
            <div class="mbr-cc-modal__overlay" aria-hidden="true"></div>
            <div class="mbr-cc-modal__content">
                <div class="mbr-cc-modal__header">
                    <h3 id="mbr-cc-modal-heading" data-mbr-cc-i18n="manage_preferences"><?php esc_html_e('Manage Cookie Preferences', 'mbr-cookie-consent'); ?></h3>
                    <button type="button" 
                            class="mbr-cc-modal__close" 
                            id="mbr-cc-modal-close"
                            aria-label="<?php esc_attr_e('Close preferences dialog', 'mbr-cookie-consent'); ?>" data-mbr-cc-i18n-attr="aria-label:close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                
                <div class="mbr-cc-modal__body">
                    <p data-mbr-cc-i18n="preferences_intro"><?php esc_html_e('We use cookies to enhance your experience. You can choose which types of cookies to allow.', 'mbr-cookie-consent'); ?></p>
                    
                    <div class="mbr-cc-categories" id="mbr-cc-categories" role="group" aria-label="<?php esc_attr_e('Cookie categories', 'mbr-cookie-consent'); ?>">
                        <?php $this->render_categories(); ?>
                    </div>
                </div>
                
                <div class="mbr-cc-modal__footer">
                    <button type="button" class="mbr-cc-btn mbr-cc-btn-accept" id="mbr-cc-save-preferences" data-mbr-cc-i18n="save_preferences">
                        <?php esc_html_e('Save Preferences', 'mbr-cookie-consent'); ?>
                    </button>
                    <button type="button" class="mbr-cc-btn mbr-cc-btn-reject" id="mbr-cc-reject-all-modal" data-mbr-cc-i18n="reject_all">
                        <?php echo esc_html($reject_text); ?>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Revisit Consent Button -->
        <?php if (get_option('mbr_cc_revisit_consent_enabled', true)) : ?>
            <button type="button" class="mbr-cc-revisit-consent" id="mbr-cc-revisit" style="display: none;" data-version="1.0.3">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                    <!-- Cookie icon - outer circle -->
                    <circle cx="12" cy="12" r="10" fill="none" stroke="currentColor" stroke-width="1.5"/>
                    <!-- Chocolate chips -->
                    <circle cx="8" cy="9" r="1.5" fill="currentColor"/>
                    <circle cx="16" cy="10" r="1.5" fill="currentColor"/>
                    <circle cx="12" cy="14" r="1.5" fill="currentColor"/>
                    <circle cx="7" cy="15" r="1" fill="currentColor"/>
                    <circle cx="16" cy="16" r="1" fill="currentColor"/>
                    <circle cx="10" cy="18" r="0.8" fill="currentColor"/>
                </svg>
                <span><?php echo esc_html(get_option('mbr_cc_revisit_consent_text', 'Cookie Settings')); ?></span>
            </button>
        <?php endif; ?>
        
        <?php
    }
    
    /**
     * Render cookie categories.
     */
    private function render_categories() {
        $categories = MBR_CC_Consent_Manager::get_instance()->get_categories();
        
        if (empty($categories)) {
            echo '<p>' . esc_html__('No cookie categories found.', 'mbr-cookie-consent') . '</p>';
            return;
        }
        
        foreach ($categories as $slug => $category) {
            $name = isset($category['name']) ? $category['name'] : ucfirst($slug);
            $description = isset($category['description']) ? $category['description'] : '';
            $required = isset($category['required']) && $category['required'];
            
            ?>
            <div class="mbr-cc-category">
                <div class="mbr-cc-category__header">
                    <label class="mbr-cc-category__label">
                        <input 
                            type="checkbox" 
                            name="mbr_cc_category[]" 
                            value="<?php echo esc_attr($slug); ?>"
                            class="mbr-cc-category__checkbox"
                            <?php checked($required); ?>
                            <?php disabled($required); ?>
                        >
                        <span class="mbr-cc-category__name"><?php echo esc_html($name); ?></span>
                        <?php if ($required) : ?>
                            <span class="mbr-cc-category__badge"><?php esc_html_e('Always Active', 'mbr-cookie-consent'); ?></span>
                        <?php endif; ?>
                    </label>
                </div>
                
                <?php if (!empty($description)) : ?>
                    <div class="mbr-cc-category__description">
                        <p><?php echo esc_html($description); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <?php
        }
    }
}
