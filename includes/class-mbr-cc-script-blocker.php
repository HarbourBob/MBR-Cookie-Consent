<?php
/**
 * Script blocker to prevent non-essential scripts from loading.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Script Blocker class.
 */
class MBR_CC_Script_Blocker {
    
    /**
     * Single instance.
     *
     * @var MBR_CC_Script_Blocker
     */
    private static $instance = null;
    
    /**
     * Blocked scripts list.
     *
     * @var array
     */
    private $blocked_scripts = array();
    
    /**
     * Get instance.
     *
     * @return MBR_CC_Script_Blocker
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
        // Start output buffering early to capture all scripts.
        add_action('template_redirect', array($this, 'start_buffer'), 1);
        
        // Load blocked scripts from settings.
        $this->load_blocked_scripts();
    }
    
    /**
     * Load blocked scripts from database.
     */
    private function load_blocked_scripts() {
        $this->blocked_scripts = get_option('mbr_cc_blocked_scripts', array());
    }
    
    /**
     * Start output buffering.
     */
    public function start_buffer() {
        // Don't buffer admin pages or AJAX requests.
        if (is_admin() || wp_doing_ajax()) {
            return;
        }
        
        ob_start(array($this, 'process_buffer'));
    }
    
    /**
     * Process buffered output and block scripts.
     *
     * @param string $buffer Page HTML.
     * @return string Modified HTML.
     */
    public function process_buffer($buffer) {
        // Check if user has given consent.
        $consent = $this->get_user_consent();
        
        // If user accepted all, don't block anything.
        if (isset($consent['all']) && $consent['all'] === true) {
            return $buffer;
        }
        
        // Block scripts based on categories.
        $buffer = $this->block_scripts_by_category($buffer, $consent);
        
        return $buffer;
    }
    
    /**
     * Get user consent from cookie.
     *
     * @return array User consent preferences.
     */
    private function get_user_consent() {
        if (!isset($_COOKIE['mbr_cc_consent'])) {
            return array();
        }
        
        $consent_data = json_decode(stripslashes($_COOKIE['mbr_cc_consent']), true);
        
        return is_array($consent_data) ? $consent_data : array();
    }
    
    /**
     * Block scripts based on cookie categories.
     *
     * @param string $html Page HTML.
     * @param array  $consent User consent.
     * @return string Modified HTML.
     */
    private function block_scripts_by_category($html, $consent) {
        // Get all blocked scripts organized by category.
        $scripts_by_category = $this->get_scripts_by_category();
        
        if (empty($scripts_by_category)) {
            return $html;
        }
        
        // Process each category.
        foreach ($scripts_by_category as $category => $scripts) {
            // Skip necessary cookies - always allowed.
            if ($category === 'necessary') {
                continue;
            }
            
            // Check if user consented to this category.
            $category_allowed = isset($consent[$category]) && $consent[$category] === true;
            
            if (!$category_allowed) {
                // Block all scripts in this category.
                foreach ($scripts as $script) {
                    $html = $this->block_script($html, $script);
                }
            }
        }
        
        return $html;
    }
    
    /**
     * Get scripts organized by category.
     *
     * @return array Scripts by category.
     */
    private function get_scripts_by_category() {
        $organized = array();
        
        foreach ($this->blocked_scripts as $script) {
            $category = $script['category'] ?? 'marketing';
            
            if (!isset($organized[$category])) {
                $organized[$category] = array();
            }
            
            $organized[$category][] = $script;
        }
        
        return $organized;
    }
    
    /**
     * Block a specific script.
     *
     * @param string $html Page HTML.
     * @param array  $script Script details.
     * @return string Modified HTML.
     */
    private function block_script($html, $script) {
        $identifier = $script['identifier'];
        $type = $script['type'] ?? 'src';
        
        if ($type === 'src') {
            // Block external scripts by src attribute.
            $html = $this->block_by_src($html, $identifier);
        } elseif ($type === 'inline') {
            // Block inline scripts by content match.
            $html = $this->block_by_content($html, $identifier);
        } elseif ($type === 'iframe') {
            // Block iframes.
            $html = $this->block_iframe($html, $identifier);
        }
        
        return $html;
    }
    
    /**
     * Block script by src attribute.
     *
     * @param string $html Page HTML.
     * @param string $src Script source pattern.
     * @return string Modified HTML.
     */
    private function block_by_src($html, $src) {
        // Match script tags with this src.
        $pattern = '/<script([^>]*)src=["\']([^"\']*' . preg_quote($src, '/') . '[^"\']*)["\']/i';
        
        $html = preg_replace_callback($pattern, function($matches) {
            // Change type to text/plain to prevent execution.
            $attributes = $matches[1];
            $src = $matches[2];
            
            // Add data attribute to identify blocked script.
            return '<script' . $attributes . ' type="text/plain" data-mbr-cc-blocked="true" data-mbr-cc-src="' . esc_attr($src) . '"';
        }, $html);
        
        return $html;
    }
    
    /**
     * Block inline script by content.
     *
     * @param string $html Page HTML.
     * @param string $content Content pattern to match.
     * @return string Modified HTML.
     */
    private function block_by_content($html, $content) {
        // Match script tags containing this content.
        $pattern = '/<script([^>]*)>(.*?' . preg_quote($content, '/') . '.*?)<\/script>/is';
        
        $html = preg_replace_callback($pattern, function($matches) {
            $attributes = $matches[1];
            $script_content = $matches[2];
            
            // Check if already has type attribute.
            if (strpos($attributes, 'type=') === false) {
                $attributes .= ' type="text/plain"';
            } else {
                // Replace existing type.
                $attributes = preg_replace('/type=["\'][^"\']*["\']/', 'type="text/plain"', $attributes);
            }
            
            return '<script' . $attributes . ' data-mbr-cc-blocked="true">' . $script_content . '</script>';
        }, $html);
        
        return $html;
    }
    
    /**
     * Block iframe.
     *
     * @param string $html Page HTML.
     * @param string $src Iframe source pattern.
     * @return string Modified HTML.
     */
    private function block_iframe($html, $src) {
        // Match iframe tags with this src.
        $pattern = '/<iframe([^>]*)src=["\']([^"\']*' . preg_quote($src, '/') . '[^"\']*)["\']/i';
        
        $html = preg_replace_callback($pattern, function($matches) {
            $attributes = $matches[1];
            $iframe_src = $matches[2];
            
            // Store original src in data attribute and remove src.
            return '<iframe' . $attributes . ' data-mbr-cc-blocked="true" data-mbr-cc-src="' . esc_attr($iframe_src) . '"';
        }, $html);
        
        return $html;
    }
    
    /**
     * Add blocked script.
     *
     * @param array $script Script details.
     * @return bool Success.
     */
    public function add_blocked_script($script) {
        $defaults = array(
            'name' => '',
            'identifier' => '',
            'type' => 'src',
            'category' => 'marketing',
            'description' => '',
        );
        
        $script = wp_parse_args($script, $defaults);
        
        // Validate required fields.
        if (empty($script['name']) || empty($script['identifier'])) {
            return false;
        }
        
        $this->blocked_scripts[] = $script;
        
        return update_option('mbr_cc_blocked_scripts', $this->blocked_scripts);
    }
    
    /**
     * Remove blocked script.
     *
     * @param int $index Script index.
     * @return bool Success.
     */
    public function remove_blocked_script($index) {
        if (!isset($this->blocked_scripts[$index])) {
            return false;
        }
        
        unset($this->blocked_scripts[$index]);
        
        // Re-index array.
        $this->blocked_scripts = array_values($this->blocked_scripts);
        
        return update_option('mbr_cc_blocked_scripts', $this->blocked_scripts);
    }
    
    /**
     * Update blocked script.
     *
     * @param int   $index Script index.
     * @param array $script Updated script details.
     * @return bool Success.
     */
    public function update_blocked_script($index, $script) {
        if (!isset($this->blocked_scripts[$index])) {
            return false;
        }
        
        $this->blocked_scripts[$index] = array_merge($this->blocked_scripts[$index], $script);
        
        return update_option('mbr_cc_blocked_scripts', $this->blocked_scripts);
    }
    
    /**
     * Get all blocked scripts.
     *
     * @return array Blocked scripts.
     */
    public function get_blocked_scripts() {
        return $this->blocked_scripts;
    }
    
    /**
     * Clear all blocked scripts.
     *
     * @return bool Success.
     */
    public function clear_blocked_scripts() {
        $this->blocked_scripts = array();
        return delete_option('mbr_cc_blocked_scripts');
    }
}
