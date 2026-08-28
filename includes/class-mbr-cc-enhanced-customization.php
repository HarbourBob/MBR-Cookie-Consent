<?php
/**
 * Enhanced Banner Customization
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Enhanced Customization class.
 */
class MBR_CC_Enhanced_Customization {

    /**
     * Coerce the page exclusion setting into a list.
     *
     * The option defaults to an array but every path that writes it sanitises
     * it as a textarea, so a string lands there as soon as anyone saves the
     * field or imports a settings file. in_array() with a string haystack is a
     * TypeError on PHP 8, and this runs on every front-end page load — so the
     * mismatch took the whole site down rather than merely misbehaving.
     *
     * One entry per line, which is how the field presents itself.
     *
     * @param mixed $value Stored option value.
     * @return array
     */
    private static function normalise_exclusions( $value ) {
        if ( is_array( $value ) ) {
            return array_values( array_filter( array_map( 'trim', $value ), 'strlen' ) );
        }

        if ( is_string( $value ) && '' !== trim( $value ) ) {
            return array_values( array_filter( array_map( 'trim', preg_split( '/[\r\n,]+/', $value ) ), 'strlen' ) );
        }

        return array();
    }

    
    /**
     * Single instance.
     *
     * @var MBR_CC_Enhanced_Customization
     */
    private static $instance = null;
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Enhanced_Customization
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
        // Add custom CSS output.
        add_action('wp_head', array($this, 'output_custom_css'), 999);
        
        // Add custom CSS textarea to settings via AJAX.
        add_action('wp_ajax_mbr_cc_save_custom_css', array($this, 'ajax_save_custom_css'));
    }
    
    /**
     * Check if banner should be shown on current page.
     *
     * @return bool Should show banner.
     */
    public static function should_show_banner() {
        // Check if globally disabled.
        if (get_option('mbr_cc_disable_banner', false)) {
            return false;
        }
        
        // Check page-specific exclusions.
        $excluded_pages = self::normalise_exclusions(get_option('mbr_cc_excluded_pages', array()));
        
        if (!empty($excluded_pages)) {
            global $post;
            
            // Check by page ID.
            if ($post && in_array($post->ID, $excluded_pages)) {
                return false;
            }
            
            // Check by post type.
            if ($post && in_array($post->post_type, $excluded_pages)) {
                return false;
            }
        }
        
        // Check URL patterns.
        $excluded_urls = get_option('mbr_cc_excluded_url_patterns', '');
        if (!empty($excluded_urls)) {
            $current_url = $_SERVER['REQUEST_URI'];
            $patterns = array_map('trim', explode("\n", $excluded_urls));
            
            foreach ($patterns as $pattern) {
                if (empty($pattern)) {
                    continue;
                }
                
                // Convert wildcards to a regex. Everything is quoted first and
                // only the wildcard is put back, so a pattern containing (, [
                // or + is matched literally instead of producing an invalid
                // expression and a warning on every page load.
                $regex = '/' . str_replace('\*', '.*', preg_quote($pattern, '/')) . '/';
                
                if (preg_match($regex, $current_url)) {
                    return false;
                }
            }
        }
        
        // Check specific page types.
        if (get_option('mbr_cc_exclude_login', false) && self::is_login_page()) {
            return false;
        }
        
        if (get_option('mbr_cc_exclude_checkout', false) && self::is_checkout_page()) {
            return false;
        }
        
        if (get_option('mbr_cc_exclude_cart', false) && self::is_cart_page()) {
            return false;
        }
        
        if (get_option('mbr_cc_exclude_account', false) && self::is_account_page()) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Whether consent is enforced on this request — that is, whether the
     * script blocker holds third-party scripts and iframes back.
     *
     * This is a different question from should_show_banner(), and conflating
     * the two was a real defect. The script blocker never consulted the
     * exclusion settings at all: it blocked everywhere, while the banner obeyed
     * them. A site owner who ticked "Hide on Checkout Pages" got scripts held
     * back on checkout with no banner offering to release them, and a
     * first-time visitor landing straight on that page had no route to consent
     * at all.
     *
     * The two settings mean genuinely different things and both are reasonable:
     *
     *   "Hide the banner here"  — usually about not interrupting a purchase.
     *                             Blocking continues, which is the correct
     *                             outcome under GDPR: no consent, no tracking.
     *                             The floating Cookie Settings button is
     *                             rendered on these pages so the visitor still
     *                             has a way to say yes if they want to.
     *
     *   "Don't enforce here"    — a deliberate decision that this page's
     *                             third-party scripts must run regardless.
     *                             Off by default, because switching it on is a
     *                             compliance choice the site owner has to make
     *                             knowingly rather than inherit.
     *
     * The exception is mbr_cc_disable_banner. With the banner switched off
     * site-wide there is no banner and no Cookie Settings button anywhere, so
     * nothing could ever release the blocked scripts. Blocking would be
     * permanent and silent, which is a trap rather than a policy — so turning
     * the banner off turns enforcement off with it.
     *
     * @return bool
     */
    public static function should_enforce_consent() {
        if (get_option('mbr_cc_disable_banner', false)) {
            return false;
        }
        
        // Blocking is independent of where the banner is displayed unless the
        // owner has explicitly asked otherwise.
        if (!get_option('mbr_cc_exclusions_skip_blocking', false)) {
            return true;
        }
        
        return self::should_show_banner();
    }
    
    /**
     * Check if current page is login page.
     *
     * @return bool Is login page.
     */
    private static function is_login_page() {
        global $pagenow;
        
        // WordPress login.
        if ($pagenow === 'wp-login.php') {
            return true;
        }
        
        // WooCommerce login.
        if (function_exists('is_account_page') && is_account_page() && !is_user_logged_in()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is checkout page.
     *
     * @return bool Is checkout page.
     */
    private static function is_checkout_page() {
        // WooCommerce checkout.
        if (function_exists('is_checkout') && is_checkout()) {
            return true;
        }
        
        // Easy Digital Downloads checkout.
        if (function_exists('edd_is_checkout') && edd_is_checkout()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is cart page.
     *
     * @return bool Is cart page.
     */
    private static function is_cart_page() {
        // WooCommerce cart.
        if (function_exists('is_cart') && is_cart()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Check if current page is account page.
     *
     * @return bool Is account page.
     */
    private static function is_account_page() {
        // WooCommerce account.
        if (function_exists('is_account_page') && is_account_page()) {
            return true;
        }
        
        // Easy Digital Downloads account.
        if (function_exists('edd_is_account_page') && edd_is_account_page()) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Output custom CSS.
     */
    public function output_custom_css() {
        $custom_css = get_option('mbr_cc_custom_css', '');
        
        if (empty($custom_css)) {
            return;
        }
        
        // Sanitize CSS: wp_strip_all_tags() removes any HTML tags, neutralising a </style> breakout and leaving only CSS rules.
        $custom_css = wp_strip_all_tags($custom_css);
        
        ?>
        <!-- MBR Cookie Consent - Custom CSS -->
        <style id="mbr-cc-custom-css">
            <?php
            // HTML escaping is intentionally not applied: the value is CSS, already stripped of tags above, and esc_html() would corrupt valid CSS such as child selectors using ">".
            echo $custom_css; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            ?>
        </style>
        <?php
    }
    
    /**
     * AJAX: Save custom CSS.
     */
    public function ajax_save_custom_css() {
        check_ajax_referer('mbr_cc_admin_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Unauthorized.'));
        }
        
        $custom_css = isset($_POST['custom_css']) ? wp_unslash($_POST['custom_css']) : '';
        
        // Sanitize CSS.
        $custom_css = wp_strip_all_tags($custom_css);
        
        update_option('mbr_cc_custom_css', $custom_css);
        
        if (class_exists('MBR_CC_Cache')) {
            MBR_CC_Cache::flush('custom_css');
        }
        
        wp_send_json_success(array('message' => 'Custom CSS saved successfully.'));
    }
    
    /**
     * Get excluded pages for display.
     *
     * @return array Pages with titles.
     */
    public static function get_excluded_pages_display() {
        $excluded_pages = get_option('mbr_cc_excluded_pages', array());
        $pages = array();
        
        foreach ($excluded_pages as $page_id) {
            $post = get_post($page_id);
            if ($post) {
                $pages[] = array(
                    'id' => $page_id,
                    'title' => get_the_title($page_id),
                    'type' => $post->post_type,
                );
            }
        }
        
        return $pages;
    }
}
