<?php
/**
 * Consent Doctor — diagnostics for "is my consent setup actually working?"
 *
 * The organising principle of this class is the distinction between what the
 * site is CONFIGURED to do and what it has been OBSERVED doing. They are kept
 * apart everywhere: in the data, in the rendering, and in the wording.
 *
 * That is not fastidiousness. Every serious fault this plugin has shipped was a
 * case where the configuration was correct and the behaviour was not, and none
 * of them were visible from the admin screens:
 *
 *   - consent decided server-side and then frozen into a cached page (2.3.3)
 *   - regional settings the banner never read, while the geolocation test tool
 *     reported them confidently and in detail (2.3.4)
 *   - video facades that left no embed to block (2.3.3)
 *   - Cloudflare geolocation handing every visitor the fallback region while
 *     the screen presented it as a detection (2.3.6)
 *   - an accessibility switch that reported the opposite of the truth in both
 *     directions (2.3.5)
 *
 * A panel that reads settings and calls them results would be the geolocation
 * test tool again, with more authority and a wider blast radius. So:
 *
 *   TIER_CONFIG   — what the settings say. Can never earn a PASS on its own.
 *                   The best it gets is INFO or NOTE: "a rule exists for this".
 *   TIER_SERVER   — something the server actually recorded happening: a consent
 *                   row written, a geolocation lookup resolved, a schema change
 *                   that took. This can PASS.
 *   TIER_BROWSER  — what a real page load actually did. Not implemented yet;
 *                   declared here so the panel can say plainly that the
 *                   strongest evidence is the evidence it does not have.
 *
 * Finding a blocking rule is not proof that no tracking request escaped. The
 * only thing that proves it is watching the requests, and until this class can
 * do that it must not imply otherwise.
 *
 * @package MBR_Cookie_Consent
 * @since   2.3.8
 */

if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_Doctor {

    /** @var MBR_CC_Doctor|null */
    private static $instance = null;

    // Evidence tiers, weakest first.
    const TIER_CONFIG  = 'config';
    const TIER_SERVER  = 'server';
    const TIER_BROWSER = 'browser';

    // Check outcomes.
    const PASS    = 'pass';     // Observed working. TIER_SERVER and above only.
    const NOTE    = 'note';     // Configured as expected. No behavioural claim.
    const WARN    = 'warn';     // Needs a human decision.
    const FAIL    = 'fail';     // Known broken.
    const UNKNOWN = 'unknown';  // Not enough evidence to say. A real answer.

    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Run every check.
     *
     * @return array {
     *     @type array $checks   List of check result arrays.
     *     @type array $summary  Counts by status.
     * }
     */
    public function run() {
        $checks = array_merge(
            $this->check_logging(),
            $this->check_region(),
            $this->check_schema(),
            $this->check_blocking_coverage(),
            $this->check_conflicts(),
            $this->check_cookie_scope(),
            $this->check_caching(),
            $this->check_unobserved()
        );

        /**
         * Add or adjust Consent Doctor checks.
         *
         * @since 2.3.8
         * @param array $checks Check results.
         */
        $checks = apply_filters('mbr_cc_doctor_checks', $checks);

        $summary = array(self::PASS => 0, self::NOTE => 0, self::WARN => 0, self::FAIL => 0, self::UNKNOWN => 0);

        foreach ($checks as $c) {
            if (isset($summary[$c['status']])) {
                $summary[$c['status']]++;
            }
        }

        return array('checks' => $checks, 'summary' => $summary);
    }

    /**
     * Build a single check result.
     *
     * @param string $id      Stable identifier.
     * @param string $tier    One of the TIER_* constants.
     * @param string $status  One of the outcome constants.
     * @param string $title   Short heading.
     * @param string $detail  What was found, in plain language.
     * @param string $action  What to do about it, or '' when nothing is needed.
     * @return array
     */
    private function result($id, $tier, $status, $title, $detail, $action = '') {
        // Enforce the rule rather than trusting every future caller to remember
        // it: configuration alone cannot report a behavioural pass.
        if ($tier === self::TIER_CONFIG && $status === self::PASS) {
            $status = self::NOTE;
        }

        return array(
            'id'     => $id,
            'tier'   => $tier,
            'status' => $status,
            'title'  => $title,
            'detail' => $detail,
            'action' => $action,
        );
    }

    // ── Observed on the server ────────────────────────────────────────────

    /**
     * Is consent logging actually writing rows?
     *
     * This is genuinely observed: either the database holds consent records
     * with timestamps or it does not, and record_log_health() notes the outcome
     * of recent attempts.
     */
    private function check_logging() {
        global $wpdb;

        $out = array();
        $table = $wpdb->prefix . 'mbr_cc_consent_logs';

        if (is_multisite()) {
            $table = $wpdb->base_prefix . 'mbr_cc_consent_logs';
        }

        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;

        if (!$exists) {
            $out[] = $this->result(
                'logging_table', self::TIER_SERVER, self::FAIL,
                __('Consent log table is missing', 'mbr-cookie-consent'),
                __('The table that stores records of consent does not exist, so nothing is being recorded. Being able to demonstrate consent is a requirement in every opt-in jurisdiction.', 'mbr-cookie-consent'),
                __('Deactivate and reactivate the plugin to rebuild the table, then check this panel again.', 'mbr-cookie-consent')
            );
            return $out;
        }

        // On Multisite the table is shared, so count this site's rows only.
        // Counting the whole table told a site its logging worked because some
        // other site on the network was writing rows.
        if (is_multisite()) {
            $blog_id = get_current_blog_id();
            $total = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE blog_id = %d", $blog_id));
            $latest = $wpdb->get_var($wpdb->prepare("SELECT timestamp FROM {$table} WHERE blog_id = %d ORDER BY timestamp DESC LIMIT 1", $blog_id));
        } else {
            $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
            $latest = $wpdb->get_var("SELECT timestamp FROM {$table} ORDER BY timestamp DESC LIMIT 1");
        }
        $failure = get_option('mbr_cc_log_last_failure', array());
        $success = get_option('mbr_cc_log_last_success', array());

        if ($total === 0) {
            $out[] = $this->result(
                'logging_rows', self::TIER_SERVER, self::UNKNOWN,
                __('No consent has been recorded yet', 'mbr-cookie-consent'),
                __('The log table exists but holds no rows. On a new or low-traffic site that is expected. On a site with visitors it means consent records are not reaching the database.', 'mbr-cookie-consent'),
                __('Open your site in a private window, make a choice on the banner, then reload this panel. A row should appear.', 'mbr-cookie-consent')
            );
        } else {
            $age = $latest ? human_time_diff(strtotime($latest), current_time('timestamp')) : '';
            $out[] = $this->result(
                'logging_rows', self::TIER_SERVER, self::PASS,
                __('Consent logging is working', 'mbr-cookie-consent'),
                $latest
                    ? sprintf(
                        /* translators: 1: number of records, 2: human-readable time difference. */
                        __('%1$s records stored. The most recent was written %2$s ago, so rows are reaching the database.', 'mbr-cookie-consent'),
                        number_format_i18n($total), $age
                    )
                    : sprintf(
                        /* translators: %s: number of records. */
                        __('%s records stored.', 'mbr-cookie-consent'),
                        number_format_i18n($total)
                    )
            );
        }

        // A recent failure matters even when rows are being written, because an
        // intermittent fault is the hardest kind to notice.
        if (is_array($failure) && !empty($failure['time'])) {
            $recent = (time() - (int) $failure['time']) < WEEK_IN_SECONDS;

            if ($recent) {
                $status_label = isset($failure['status']) ? $failure['status'] : 'unknown';
                $out[] = $this->result(
                    'logging_failures', self::TIER_SERVER, self::WARN,
                    __('A consent log write failed recently', 'mbr-cookie-consent'),
                    sprintf(
                        /* translators: 1: failure type, 2: human-readable time difference. */
                        __('The last failure was recorded %2$s ago, of type "%1$s". Rows may still be written most of the time — an intermittent fault will not show up in the record count.', 'mbr-cookie-consent'),
                        $status_label,
                        human_time_diff((int) $failure['time'], time())
                    ),
                    'throttled' === $status_label
                        ? __('This is the rate limiter. It is expected under bursts of traffic and no action is needed unless it is constant.', 'mbr-cookie-consent')
                        : __('Check your database error log around that time.', 'mbr-cookie-consent')
                );
            }
        }

        unset($success);

        return $out;
    }

    /**
     * Which region is actually being applied, and did anything detect it?
     *
     * get_detection_source() exists precisely because a fallback presented as a
     * detection is how a site sits in the wrong privacy regime indefinitely.
     */
    private function check_region() {
        if (!class_exists('MBR_CC_Geolocation')) {
            return array();
        }

        if (!get_option('mbr_cc_geolocation_enabled', false)) {
            $default = get_option('mbr_cc_geolocation_default', 'US');

            return array($this->result(
                'region_source', self::TIER_CONFIG, self::NOTE,
                __('Geolocation is switched off', 'mbr-cookie-consent'),
                sprintf(
                    /* translators: %s: country code. */
                    __('Every visitor is being shown the %s configuration, wherever they are. That is a legitimate choice — a single strict banner for everyone is compliant everywhere — but it is a choice, so it is worth confirming it is the one you meant.', 'mbr-cookie-consent'),
                    esc_html($default)
                )
            ));
        }

        $geo = MBR_CC_Geolocation::get_instance();
        $source = method_exists($geo, 'get_detection_source') ? $geo->get_detection_source() : 'unknown';
        $country = $geo->get_country();
        $region = method_exists($geo, 'get_region_name') ? $geo->get_region_name() : '';

        $map = array(
            'cloudflare' => array(
                self::PASS,
                __('Region resolved from Cloudflare', 'mbr-cookie-consent'),
                __('Your CDN reported the country directly. No outbound lookup is made and no visitor IP address is sent to a third party.', 'mbr-cookie-consent'),
                '',
            ),
            'provider' => array(
                self::PASS,
                __('Region resolved by lookup', 'mbr-cookie-consent'),
                __('Your configured IP lookup provider answered.', 'mbr-cookie-consent'),
                '',
            ),
            'ipapi_fallback' => array(
                self::WARN,
                __('Region resolved, but not by your chosen provider', 'mbr-cookie-consent'),
                __('ip-api.com is selected but cannot be used as configured, so ipapi.co answered instead.', 'mbr-cookie-consent'),
                __('Add an ip-api.com pro key, or select ipapi.co, on the Geolocation tab.', 'mbr-cookie-consent'),
            ),
            'default' => array(
                self::FAIL,
                __('No region was detected', 'mbr-cookie-consent'),
                __('This is your configured default region, not a lookup result. Every visitor whose location cannot be resolved is being shown it — so if it is not the region you expect, your visitors are seeing the wrong banner.', 'mbr-cookie-consent'),
                __('Common causes: outbound requests blocked by your host, the provider rate-limiting you, or a provider that cannot answer as configured. See the Geolocation tab.', 'mbr-cookie-consent'),
            ),
        );

        $row = isset($map[$source]) ? $map[$source] : array(
            self::UNKNOWN,
            __('Region source unclear', 'mbr-cookie-consent'),
            __('The detection source could not be determined for this request.', 'mbr-cookie-consent'),
            '',
        );

        $out = array();

        // Say whether this answer was resolved now or read from cache, and how
        // old it is. Without that the check is a coin toss: a success is cached
        // for 24 hours and a fallback for five minutes, so re-running it after
        // a failure almost always shows a pass, and the pass then persists for
        // a day whether or not the provider still works. Reporting a day-old
        // cached answer as "observed working" is exactly the kind of confident
        // wrongness this panel exists to avoid.
        $diag = method_exists($geo, 'get_detection_diagnostics') ? $geo->get_detection_diagnostics() : array();
        $from_cache = !empty($diag['from_cache']);
        $cached_at = isset($diag['cached_at']) ? (int) $diag['cached_at'] : 0;

        $freshness = $from_cache
            ? sprintf(
                /* translators: %s: human-readable time difference. */
                __('This answer was cached %s ago, not resolved just now, so it does not show whether the provider is reachable at this moment.', 'mbr-cookie-consent'),
                $cached_at ? human_time_diff($cached_at, time()) : __('earlier', 'mbr-cookie-consent')
            )
            : __('Resolved during this request.', 'mbr-cookie-consent');

        $status = $row[0];

        // A cached success is not an observation of current health, so it may
        // not claim one.
        if ($status === self::PASS && $from_cache) {
            $status = self::NOTE;
        }

        $out[] = $this->result(
            'region_source', self::TIER_SERVER, $status, $row[1],
            trim(sprintf(
                /* translators: 1: country code, 2: region name, 3: explanation, 4: freshness note. */
                __('Currently resolving to %1$s (%2$s). %3$s %4$s', 'mbr-cookie-consent'),
                esc_html($country ? $country : '—'),
                esc_html($region ? $region : '—'),
                $row[2],
                $freshness
            )),
            $row[3]
        );

        // Intermittent failures are the ones nobody catches, because the
        // five-minute fallback cache clears them from view long before anyone
        // looks. A recorded fallback is worth reporting even when the current
        // answer is fine.
        $fallback = isset($diag['last_fallback']) ? $diag['last_fallback'] : array();

        if (is_array($fallback) && !empty($fallback['time'])) {
            $age = time() - (int) $fallback['time'];

            if ($age < DAY_IN_SECONDS && $status !== self::FAIL) {
                $out[] = $this->result(
                    'region_intermittent', self::TIER_SERVER, self::WARN,
                    __('A geolocation lookup fell back recently', 'mbr-cookie-consent'),
                    sprintf(
                        /* translators: %s: human-readable time difference. */
                        __('Within the last day — %s ago — a lookup failed to resolve and a visitor was given your default region instead. Geolocation is working now, so this was intermittent rather than broken. Visitors served during that window saw the wrong region.', 'mbr-cookie-consent'),
                        human_time_diff((int) $fallback['time'], time())
                    ),
                    __('Occasional fallbacks happen with free lookup services. If it is frequent, consider Cloudflare headers, which need no outbound request and cannot be rate-limited.', 'mbr-cookie-consent')
                );
            }
        }

        return $out;
    }

    /** Did the 2.3.7 consent-log schema change actually take? */
    private function check_schema() {
        if (!class_exists('MBR_CC_Database')) {
            return array();
        }

        $failed = get_option('mbr_cc_schema_237_failed', false);

        if (!$failed) {
            return array();
        }

        return array($this->result(
            'schema_237', self::TIER_SERVER, self::WARN,
            __('A database index could not be created', 'mbr-cookie-consent'),
            __('The unique index that de-duplicates retried consent log writes is missing. Consent logging still works; it can just record the same event twice if a write is retried.', 'mbr-cookie-consent'),
            __('Usually a database user without ALTER permission. Ask your host, then deactivate and reactivate the plugin.', 'mbr-cookie-consent')
        ));
    }

    // ── Configured ────────────────────────────────────────────────────────

    /** What do the blocking rules cover, and what is left over? */
    private function check_blocking_coverage() {
        $out = array();

        if (!class_exists('MBR_CC_Script_Blocker')) {
            return $out;
        }

        $builtin = MBR_CC_Script_Blocker::get_builtin_services();

        // Read the option rather than asking the blocker singleton. The blocker
        // loads its rules once at construction, early in the request; by the
        // time this panel runs, a rule added on this page load would be missing
        // from its copy and the panel would under-report. Diagnostics should
        // read the source of truth, not a cache of it.
        $custom = get_option('mbr_cc_blocked_scripts', array());

        $builtin_count = 0;
        $by_category = array();

        foreach ((array) $builtin as $category => $services) {
            $builtin_count += count($services);
            $by_category[$category] = count($services);
        }

        $parts = array();

        foreach ($by_category as $category => $n) {
            $parts[] = sprintf('%s (%d)', ucfirst($category), $n);
        }

        $out[] = $this->result(
            'blocking_rules', self::TIER_CONFIG, self::NOTE,
            __('Blocking rules in place', 'mbr-cookie-consent'),
            sprintf(
                /* translators: 1: number of built-in rules, 2: per-category breakdown, 3: number of custom rules. */
                __('%1$d built-in service rules — %2$s — plus %3$d you have added. A rule means the plugin knows how to hold that service. It is not evidence that it did.', 'mbr-cookie-consent'),
                $builtin_count,
                implode(', ', $parts),
                count((array) $custom)
            )
        );

        // Script blocking switched off entirely is worth saying loudly.
        if (!get_option('mbr_cc_enable_script_blocking', true)) {
            $out[] = $this->result(
                'blocking_enabled', self::TIER_CONFIG, self::FAIL,
                __('Automatic script blocking is switched off', 'mbr-cookie-consent'),
                __('Non-essential scripts will run before the visitor has consented. The banner still collects a choice, but nothing acts on it, which is the arrangement regulators specifically object to.', 'mbr-cookie-consent'),
                __('Enable script blocking on the Content Blocking tab.', 'mbr-cookie-consent')
            );
        }

        // Exclusions that also stop consent being enforced.
        if (get_option('mbr_cc_exclusions_skip_blocking', false)) {
            $pages = get_option('mbr_cc_excluded_pages', array());
            $count = is_array($pages) ? count($pages) : 0;

            $out[] = $this->result(
                'blocking_exclusions', self::TIER_CONFIG, self::WARN,
                __('Some pages do not enforce consent', 'mbr-cookie-consent'),
                sprintf(
                    /* translators: %d: number of excluded pages. */
                    __('Third-party scripts are allowed to run on %d excluded page(s) regardless of consent. This is off by default because it is a compliance decision rather than a display preference.', 'mbr-cookie-consent'),
                    $count
                ),
                __('If you only meant to hide the banner on those pages, turn this off — hiding the banner already leaves blocking in place.', 'mbr-cookie-consent')
            );
        }

        return $out;
    }

    /** Other consent plugins, and duplicate tag managers. */
    private function check_conflicts() {
        $out = array();

        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        // Matched on the plugin's own directory name, so a renamed fork is
        // missed. That is the honest limit of a configuration check.
        $known = array(
            'cookie-law-info'              => 'CookieYes / GDPR Cookie Consent',
            'cookie-notice'                => 'Cookie Notice & Compliance',
            'complianz-gdpr'               => 'Complianz',
            'gdpr-cookie-compliance'       => 'Moove GDPR Cookie Compliance',
            'cookiebot'                    => 'Cookiebot',
            'wp-gdpr-compliance'           => 'WP GDPR Compliance',
            'iubenda-cookie-law-solution'  => 'iubenda',
            'beautiful-and-responsive-cookie-consent' => 'Beautiful Cookie Consent Banner',
            'termly'                       => 'Termly',
            'real-cookie-banner'           => 'Real Cookie Banner',
        );

        $found = array();

        foreach ((array) get_option('active_plugins', array()) as $path) {
            $slug = dirname($path);

            if (isset($known[$slug])) {
                $found[] = $known[$slug];
            }
        }

        if ($found) {
            $out[] = $this->result(
                'conflict_plugins', self::TIER_CONFIG, self::FAIL,
                __('Another consent plugin is active', 'mbr-cookie-consent'),
                sprintf(
                    /* translators: %s: comma-separated plugin names. */
                    __('Found: %s. Two consent managers on one site will fight: two banners, two cookies, and each one unblocking scripts the other is holding. Whichever runs last usually wins, which is not something you want decided by load order.', 'mbr-cookie-consent'),
                    implode(', ', $found)
                ),
                __('Deactivate one of them before relying on either.', 'mbr-cookie-consent')
            );
        } else {
            $out[] = $this->result(
                'conflict_plugins', self::TIER_CONFIG, self::NOTE,
                __('No conflicting consent plugin detected', 'mbr-cookie-consent'),
                __('None of the consent plugins this check knows about are active. It matches on plugin folder names, so a renamed or unlisted plugin would not be spotted.', 'mbr-cookie-consent')
            );
        }

        // Duplicate Google tags: countable in the rules, not in the page.
        $google = 0;

        foreach ((array) get_option('mbr_cc_blocked_scripts', array()) as $script) {
            $hay = '';

            foreach ((array) $script as $v) {
                if (is_string($v)) {
                    $hay .= ' ' . strtolower($v);
                }
            }

            if (strpos($hay, 'googletagmanager') !== false || strpos($hay, 'google-analytics') !== false || strpos($hay, 'gtag') !== false) {
                $google++;
            }
        }

        if ($google > 1) {
            $out[] = $this->result(
                'duplicate_google', self::TIER_CONFIG, self::WARN,
                __('Several Google tag rules are registered', 'mbr-cookie-consent'),
                sprintf(
                    /* translators: %d: number of rules. */
                    __('%d of your custom rules refer to Google tags. That is fine if deliberate, but a site with both a hard-coded gtag.js and a Tag Manager container often double-counts and can send two different consent signals.', 'mbr-cookie-consent'),
                    $google
                ),
                __('Only a live page load can confirm how many Google tags actually fire. See the note at the end of this panel.', 'mbr-cookie-consent')
            );
        }

        return $out;
    }

    /** Cookie scope: secure flag, domain, subdomain sharing. */
    private function check_cookie_scope() {
        $out = array();

        if (!is_ssl() && !(defined('FORCE_SSL_ADMIN') && FORCE_SSL_ADMIN)) {
            $out[] = $this->result(
                'cookie_secure', self::TIER_CONFIG, self::WARN,
                __('Site is not using HTTPS', 'mbr-cookie-consent'),
                __('The consent cookie cannot be marked Secure, so it travels in cleartext and can be read or altered in transit. Consent records taken over plain HTTP are also weaker evidence.', 'mbr-cookie-consent'),
                __('Move the site to HTTPS. Every other check here assumes it.', 'mbr-cookie-consent')
            );
        }

        if (get_option('mbr_cc_share_consent_subdomains', false)) {
            $host = wp_parse_url(home_url(), PHP_URL_HOST);

            $out[] = $this->result(
                'cookie_subdomain', self::TIER_CONFIG, self::NOTE,
                __('Consent is shared across subdomains', 'mbr-cookie-consent'),
                sprintf(
                    /* translators: %s: site hostname. */
                    __('A choice made on %s will apply to its subdomains. Multi-part suffixes such as .co.uk are handled. Only share consent between sites that are genuinely the same data controller.', 'mbr-cookie-consent'),
                    esc_html((string) $host)
                )
            );
        }

        return $out;
    }

    /** Page caching, and whether purging is wired up for it. */
    private function check_caching() {
        $known = array(
            'WP_Rocket'        => 'WP Rocket',
            'LiteSpeed\\Core'  => 'LiteSpeed Cache',
            'W3TC'             => 'W3 Total Cache',
            'WpeCommon'        => 'WP Engine',
            'Breeze_Admin'     => 'Breeze',
            'autoptimizeCache' => 'Autoptimize',
        );

        $active = array();

        foreach ($known as $class => $label) {
            if (class_exists($class)) {
                $active[] = $label;
            }
        }

        if (function_exists('sg_cachepress_purge_cache')) {
            $active[] = 'SiteGround Speed Optimizer';
        }

        if (function_exists('wp_cache_clear_cache')) {
            $active[] = 'WP Super Cache';
        }

        if (defined('WP_CACHE') && WP_CACHE && !$active) {
            return array($this->result(
                'cache_unknown', self::TIER_CONFIG, self::WARN,
                __('A page cache is active but was not recognised', 'mbr-cookie-consent'),
                __('WP_CACHE is on and no cache plugin this version knows about was found. Saving your settings will not purge it, so banner changes may not appear on the front end until the cache expires.', 'mbr-cookie-consent'),
                __('Purge manually after saving, or hook your cache into the mbr_cc_flush_caches_after action. Tell me which plugin it is and it can be added.', 'mbr-cookie-consent')
            ));
        }

        if (!$active) {
            return array();
        }

        return array($this->result(
            'cache_known', self::TIER_CONFIG, self::NOTE,
            __('Page cache detected and purging is wired up', 'mbr-cookie-consent'),
            sprintf(
                /* translators: %s: comma-separated cache plugin names. */
                __('Found: %s. Saving settings purges it automatically. Since 2.3.4 nothing that varies by visitor is written into the page, so a cached copy is safe to share — but that only holds for pages generated by this version.', 'mbr-cookie-consent'),
                implode(', ', $active)
            ),
            __('If you have upgraded recently, purge once by hand so no page built by an older version is still being served.', 'mbr-cookie-consent')
        ));
    }

    // ── The evidence this panel does not have ────────────────────────────

    /**
     * Report what a real page load actually did — or that none has been seen.
     *
     * This is the only tier that can answer the question the panel exists for.
     * Everything above establishes that rules exist; this establishes whether
     * anything got past them.
     */
    private function check_unobserved() {
        if (!class_exists('MBR_CC_Doctor_Probe')) {
            return array();
        }

        $probe = MBR_CC_Doctor_Probe::last_result();

        if (!$probe) {
            return array($this->result(
                'browser_evidence', self::TIER_BROWSER, self::UNKNOWN,
                __('No live check has been run yet', 'mbr-cookie-consent'),
                __('Every check above reads your configuration or your database. A blocking rule shows the plugin knows how to hold a service; it does not show that nothing slipped past. Scripts injected at runtime are invisible to a server-side audit entirely.', 'mbr-cookie-consent'),
                __('Use Run live check above. It loads your home page in a hidden frame and records the network requests it actually makes.', 'mbr-cookie-consent')
            ));
        }

        $out = array();
        $age = human_time_diff((int) $probe['time'], time());

        // Name the page. Findings are about one URL, and once the check can be
        // pointed anywhere, a pass with no page attached reads as a pass for the
        // whole site — which is exactly the overclaim this panel exists to
        // avoid. The home page is usually the least revealing page there is.
        $target = '';

        if (!empty($probe['target'])) {
            $target = $probe['target'];
        } elseif (!empty($probe['url'])) {
            $target = $probe['url'];
        }

        $where = $target
            ? sprintf(
                /* translators: %s: URL that was checked. */
                __('Page checked: %s.', 'mbr-cookie-consent'),
                esc_url_raw($target)
            )
            : '';

        // Conditions first. A result gathered under a content blocker, or on a
        // page that had already been consented to, does not mean what it looks
        // like it means — and must not be read as a pass.
        $unreliable = false;

        $blocker = isset($probe['blocker']) && is_array($probe['blocker']) ? $probe['blocker'] : array();
        $bait_hidden = !empty($blocker['hiddenBait']);
        $fetch_blocked = !empty($blocker['fetchBlocked']);
        // Older results stored a single boolean; treat those as a bare signal.
        $legacy_flag = !is_array(isset($probe['blocker']) ? $probe['blocker'] : null) && !empty($probe['blocker']);

        if ($bait_hidden || $fetch_blocked || $legacy_flag) {
            $unreliable = true;

            $signals = array();

            if ($bait_hidden) {
                $signals[] = __('an element with advert-like class names was hidden', 'mbr-cookie-consent');
            }

            if ($fetch_blocked) {
                $signals[] = __('a request to a file on your own site with an advert-like name was refused', 'mbr-cookie-consent');
            }

            $out[] = $this->result(
                'browser_blocker', self::TIER_BROWSER, self::WARN,
                __('A content blocker appears to have been active', 'mbr-cookie-consent'),
                $signals
                    ? sprintf(
                        /* translators: %s: list of detection signals. */
                        __('Detected because %s. Requests were probably suppressed by an extension in your own browser, so anything reported below is incomplete — a clean result under a content blocker tells you nothing about what your visitors receive.', 'mbr-cookie-consent'),
                        implode(__(', and ', 'mbr-cookie-consent'), $signals)
                    )
                    : __('Requests were probably suppressed by an extension in your own browser, so anything reported below is incomplete.', 'mbr-cookie-consent'),
                __('Re-run with your blocker disabled for this site, or in a browser profile without one.', 'mbr-cookie-consent')
            );
        } elseif (isset($blocker['canMeasure']) && !$blocker['canMeasure']) {
            $unreliable = true;
            $out[] = $this->result(
                'browser_blocker', self::TIER_BROWSER, self::UNKNOWN,
                __('Could not tell whether a content blocker was running', 'mbr-cookie-consent'),
                __('The page the check loaded was not laid out by the browser, so the detection could not measure anything. That is not evidence either way, and results below should be treated as incomplete.', 'mbr-cookie-consent'),
                __('Re-run the check. If it keeps reporting this, let me know which browser you are using.', 'mbr-cookie-consent')
            );
        }

        if (!empty($probe['consented']) && empty($probe['simulated'])) {
            $unreliable = true;
            $out[] = $this->result(
                'browser_consented', self::TIER_BROWSER, self::WARN,
                __('The page checked had already been consented to', 'mbr-cookie-consent'),
                __('A consent choice was present, so this measured what a consenting visitor receives. Third-party requests are expected in that case and prove nothing about pre-consent behaviour.', 'mbr-cookie-consent'),
                __('Re-run with "simulate a first-time visitor" enabled.', 'mbr-cookie-consent')
            );
        }

        // When the page under test had already been consented to, third-party
        // requests are the correct outcome, not a failure. Reporting them as
        // one would train people to ignore the panel — and a diagnostic that
        // cries wolf is worse than none, because the real finding is then
        // indistinguishable from the noise.
        $consented_load = !empty($probe['consented']) && empty($probe['simulated']);
        $finding_status = $consented_load ? self::NOTE : self::FAIL;

        $requests = isset($probe['requests']) && is_array($probe['requests']) ? $probe['requests'] : array();
        $unknown = array();
        $known = array();

        foreach ($requests as $r) {
            if (!empty($r['known'])) {
                $known[] = $r;
            } else {
                $unknown[] = $r;
            }
        }

        if (!$requests) {
            $out[] = $this->result(
                'browser_requests', self::TIER_BROWSER,
                $unreliable ? self::UNKNOWN : self::PASS,
                __('No third-party requests before consent', 'mbr-cookie-consent'),
                $unreliable
                    ? sprintf(
                        /* translators: %s: human-readable time difference. */
                        __('The check %s ago recorded no third-party requests, but it ran under conditions that suppress them. Treat this as inconclusive.', 'mbr-cookie-consent'),
                        $age
                    )
                    : trim(sprintf(
                        /* translators: 1: human-readable time difference, 2: page checked. */
                        __('The check %1$s ago loaded this page as a first-time visitor and recorded no requests to any third-party host. %2$s', 'mbr-cookie-consent'),
                        $age,
                        $where
                    )),
                __('This is evidence about one page. Check a post with comments and a page with an embedded video too — those are where tracking usually escapes.', 'mbr-cookie-consent')
            );

            return $out;
        }

        if ($unknown) {
            $names = array();

            foreach (array_slice($unknown, 0, 8) as $u) {
                $names[] = $u['host'];
            }

            $out[] = $this->result(
                'browser_unclassified', self::TIER_BROWSER, $finding_status,
                $consented_load
                    ? __('Unrecognised third-party hosts were contacted', 'mbr-cookie-consent')
                    : __('Unrecognised third-party requests were made before consent', 'mbr-cookie-consent'),
                sprintf(
                    /* translators: 1: number of hosts, 2: comma-separated hostnames. */
                    _n(
                        '%1$d host was contacted that no blocking rule covers: %2$s. Nothing was holding it, so it ran before the visitor chose anything.',
                        '%1$d hosts were contacted that no blocking rule covers: %2$s. Nothing was holding them, so they ran before the visitor chose anything.',
                        count($unknown),
                        'mbr-cookie-consent'
                    ),
                    count($unknown),
                    implode(', ', $names)
                ) . ($where ? ' ' . $where : ''),
                $consented_load
                    ? __('This load had consent, so these may be legitimate. Re-run as a first-time visitor to find out whether they fire before a choice is made.', 'mbr-cookie-consent')
                    : __('Add each one on the Content Blocking tab, under the category it belongs to, then run the check again.', 'mbr-cookie-consent')
            );
        }

        if ($known) {
            $names = array();

            foreach (array_slice($known, 0, 8) as $k) {
                $names[] = $k['host'] . ' (' . $k['service'] . ')';
            }

            // Say why, using the initiator Resource Timing reported, rather
            // than guessing. The stock explanation — "injected at runtime by a
            // tag manager" — is wrong for an <img>, and sends people hunting
            // through their tag manager for something that was never there.
            $initiators = array();

            foreach ($known as $k) {
                if (!empty($k['initiator'])) {
                    $initiators[strtolower($k['initiator'])] = true;
                }
            }

            // Images and stylesheets are different faults with different
            // fixes, and lumping them together produced advice that was simply
            // wrong for a web font: it named the <img> rule type for a
            // <link rel="stylesheet">, and told the site owner to re-add a rule
            // that ships built in and cannot be re-added.
            $is_image = isset($initiators['img']) || isset($initiators['image']);
            $is_style = isset($initiators['link']) || isset($initiators['css'])
                || isset($initiators['font']);

            if ($is_style) {
                $why = __('At least one of these arrived as a stylesheet or web font, not a script. Stylesheet blocking needs version 2.4.5 or later; before that, a rule against a font or CSS host was listed but could never fire. If you are on 2.4.5 and it still escapes, check that nothing outputs the tag after the consent layer has run.', 'mbr-cookie-consent');
            } elseif ($is_image) {
                $why = __('At least one of these was requested by an image, not a script. Custom rules of type External, Inline or Iframe only rewrite <script> and <iframe> tags, so they cannot hold an <img>. Re-add the rule with type Image.', 'mbr-cookie-consent');
            } else {
                $why = __('Usually the script is injected at runtime by a tag manager or another plugin, outside the consent layer. Check whether anything else on the site loads it.', 'mbr-cookie-consent');
            }

            // Web fonts have a fix better than blocking them.
            foreach ($known as $k) {
                if (strpos($k['host'], 'fonts.googleapis.com') !== false
                    || strpos($k['host'], 'fonts.gstatic.com') !== false) {
                    $why .= ' ' . __('For Google Fonts specifically, self-hosting the font files removes the disclosure entirely and avoids visitors seeing a fallback typeface until they consent — which is usually the better answer.', 'mbr-cookie-consent');
                    break;
                }
            }

            $out[] = $this->result(
                'browser_escaped', self::TIER_BROWSER, $finding_status,
                $consented_load
                    ? __('Known services were contacted, as expected for a consented load', 'mbr-cookie-consent')
                    : __('Services with blocking rules still made requests', 'mbr-cookie-consent'),
                $consented_load
                    ? sprintf(
                        /* translators: %s: comma-separated hostnames with service names. */
                        __('These hosts were contacted: %s. All are covered by blocking rules, and a visitor who has consented is meant to receive them. This says nothing about pre-consent behaviour.', 'mbr-cookie-consent'),
                        implode(', ', $names)
                    )
                    : sprintf(
                        /* translators: 1: comma-separated hostnames with service names, 2: page checked. */
                        __('These hosts have rules that should have held them, and they were contacted anyway: %1$s. A rule that does not fire is the failure this panel was built to catch. %2$s', 'mbr-cookie-consent'),
                        implode(', ', $names),
                        $where
                    ),
                $consented_load
                    ? __('Re-run as a first-time visitor to test what happens before consent.', 'mbr-cookie-consent')
                    : $why
            );
        }

        return $out;
    }

    // ── Presentation helpers ─────────────────────────────────────────────

    /** @return array status => label */
    public static function status_labels() {
        return array(
            self::PASS    => __('Observed working', 'mbr-cookie-consent'),
            self::NOTE    => __('Configured', 'mbr-cookie-consent'),
            self::WARN    => __('Needs review', 'mbr-cookie-consent'),
            self::FAIL    => __('Problem', 'mbr-cookie-consent'),
            self::UNKNOWN => __('Not known', 'mbr-cookie-consent'),
        );
    }

    /** @return array tier => label */
    public static function tier_labels() {
        return array(
            self::TIER_CONFIG  => __('Configuration', 'mbr-cookie-consent'),
            self::TIER_SERVER  => __('Observed on the server', 'mbr-cookie-consent'),
            self::TIER_BROWSER => __('Observed in a browser', 'mbr-cookie-consent'),
        );
    }

    /** @return array tier => one-line explanation of what that evidence is worth */
    public static function tier_descriptions() {
        return array(
            self::TIER_CONFIG  => __('What your settings say. This is what the plugin intends to do, not a record of it happening.', 'mbr-cookie-consent'),
            self::TIER_SERVER  => __('Things the server actually recorded: rows written, lookups resolved, schema changes that took.', 'mbr-cookie-consent'),
            self::TIER_BROWSER => __('What a real page load actually did — the network requests it made, recorded in your browser. The strongest evidence available.', 'mbr-cookie-consent'),
        );
    }
}
