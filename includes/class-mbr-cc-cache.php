<?php
/**
 * Page cache invalidation.
 *
 * @package MBR_Cookie_Consent
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Purges third-party page caches when plugin settings change.
 *
 * The banner is rendered server-side into the page, so its heading, wording,
 * button labels, colours and category list are all baked into whatever a page
 * cache has already stored. Changing a setting therefore has no visible effect
 * on a cached front end until something purges it — which looked, to more than
 * one person, like the setting had not saved at all.
 *
 * Nothing here touches OPcache. That caches compiled PHP files and is only
 * relevant when a .php file on disk changes, which a settings save does not do.
 */
class MBR_CC_Cache {
    
    /**
     * Whether a flush has been queued for this request.
     *
     * @var bool
     */
    private static $queued = false;
    
    /**
     * Why the flush was queued, for the benefit of the action hooks.
     *
     * @var string
     */
    private static $reason = '';
    
    /**
     * Register the automatic option watcher.
     *
     * The explicit MBR_CC_Cache::flush() calls in the save handlers say plainly
     * what each one is for. This is the net underneath them: any admin-side
     * write to an mbr_cc_ option purges too, so a handler added later cannot
     * quietly reintroduce the stale-banner problem by forgetting to call it.
     *
     * Bound to admin_init, which covers admin-ajax.php but never a front-end
     * request — important, because the front end writes options of its own and
     * purging the page cache on visitor traffic would be ruinous.
     *
     * @return void
     */
    public static function init() {
        add_action('admin_init', array(__CLASS__, 'watch_options'));
    }
    
    /**
     * Hook option writes.
     *
     * @return void
     */
    public static function watch_options() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        add_action('updated_option', array(__CLASS__, 'maybe_flush_for_option'), 10, 1);
        add_action('added_option', array(__CLASS__, 'maybe_flush_for_option'), 10, 1);
    }
    
    /**
     * Queue a flush if the written option affects the front end.
     *
     * @param string $option Option name.
     * @return void
     */
    public static function maybe_flush_for_option($option) {
        if (!is_string($option) || strpos($option, 'mbr_cc_') !== 0) {
            return;
        }
        
        // Internal bookkeeping. These change nothing a visitor can see, and
        // several are written during the upgrade routine on an ordinary admin
        // page load — purging every cache because a version marker moved would
        // be a poor trade.
        $ignore = array(
            'mbr_cc_version',
            'mbr_cc_db_version',
            'mbr_cc_import_backup',
            'mbr_cc_privacy_policy_regenerated',
            'mbr_cc_230_default_region_preserved',
            'mbr_cc_231_geo_provider_switched',
            'mbr_cc_232_multilingual_precedence',
        );
        
        /**
         * Filter the options that do not warrant a cache purge.
         *
         * @param string[] $ignore Option names.
         */
        $ignore = apply_filters('mbr_cc_cache_ignored_options', $ignore);
        
        if (in_array($option, (array) $ignore, true)) {
            return;
        }
        
        self::flush('option:' . $option);
    }
    
    /**
     * Queue a cache flush for the end of the current request.
     *
     * Deliberately deferred to `shutdown` rather than run immediately. A single
     * request can write many options — a settings import writes dozens — and
     * purging partway through would cache the half-written state straight back
     * in. Running once, after everything has been written, also means the
     * caller does not have to think about ordering.
     *
     * @param string $reason Short identifier for what triggered the flush.
     * @return void
     */
    public static function flush($reason = '') {
        if (self::$queued) {
            return;
        }
        
        self::$queued = true;
        self::$reason = is_string($reason) ? $reason : '';
        
        add_action('shutdown', array(__CLASS__, 'run'), 20);
    }
    
    /**
     * Perform the flush.
     *
     * Public because it is a hook callback; call flush() rather than this.
     *
     * @return void
     */
    public static function run() {
        $reason = self::$reason;
        
        /**
         * Filter whether plugin settings changes should purge page caches.
         *
         * Returning false leaves every cache alone. Useful on hosts that purge
         * on their own schedule, or where an agency would rather control this
         * from a deploy script.
         *
         * @param bool   $enabled Whether to flush. Default true.
         * @param string $reason  What triggered the flush.
         */
        if (!apply_filters('mbr_cc_flush_caches', true, $reason)) {
            return;
        }
        
        /**
         * Fires before third-party caches are purged.
         *
         * @param string $reason What triggered the flush.
         */
        do_action('mbr_cc_before_flush_caches', $reason);
        
        self::flush_siteground();
        self::flush_wp_rocket();
        self::flush_others();
        
        /**
         * Fires after third-party caches are purged.
         *
         * Anything not covered above can be hung here.
         *
         * @param string $reason What triggered the flush.
         */
        do_action('mbr_cc_flush_caches_after', $reason);
    }
    
    /**
     * SiteGround Speed Optimizer (formerly SG Optimizer).
     *
     * The helper function is the documented entry point and has survived every
     * rename so far; the class is checked as a fallback in case a future
     * release drops it. Both are guarded, because a purge failing is a nuisance
     * but a fatal on the settings screen is not.
     *
     * @return void
     */
    private static function flush_siteground() {
        if (function_exists('sg_cachepress_purge_cache')) {
            sg_cachepress_purge_cache();
            return;
        }
        
        $supercacher = '\SiteGround_Optimizer\Supercacher\Supercacher';
        
        if (class_exists($supercacher) && method_exists($supercacher, 'purge_cache')) {
            call_user_func(array($supercacher, 'purge_cache'));
        }
    }
    
    /**
     * WP Rocket.
     *
     * Only the page cache is cleared. Minified assets are untouched: the banner
     * stylesheet is unchanged by a settings save, and its colours are emitted
     * inline rather than compiled into a file.
     *
     * @return void
     */
    private static function flush_wp_rocket() {
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
    }
    
    /**
     * Everything else in common use.
     *
     * @return void
     */
    private static function flush_others() {
        // LiteSpeed Cache.
        do_action('litespeed_purge_all');
        
        // Nginx Helper.
        do_action('rt_nginx_helper_purge_all');
        
        // Cache Enabler.
        do_action('cache_enabler_clear_complete_cache');
        
        // Breeze.
        do_action('breeze_clear_all_cache');
        
        // WP-Optimize.
        do_action('wpo_cache_flush');
        
        // W3 Total Cache.
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        
        // WP Super Cache.
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }
        
        // WP Fastest Cache.
        if (isset($GLOBALS['wp_fastest_cache']) && method_exists($GLOBALS['wp_fastest_cache'], 'deleteCache')) {
            $GLOBALS['wp_fastest_cache']->deleteCache(true);
        }
        
        // WP Engine.
        if (class_exists('WpeCommon') && method_exists('WpeCommon', 'purge_varnish_cache')) {
            call_user_func(array('WpeCommon', 'purge_varnish_cache'));
        }
    }
}
