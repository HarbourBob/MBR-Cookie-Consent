<?php
/**
 * Geolocation Detection Class
 * Detects user location and applies appropriate privacy regulations
 *
 * @package MBR_Cookie_Consent
 * @version 2.1.2
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

class MBR_CC_Geolocation {
    
    /**
     * Singleton instance
     */
    private static $instance = null;
    
    /**
     * User's detected country code
     */
    private $country_code = null;
    
    /**
     * User's detected region/state/province code (where available)
     * Used for sub-national regimes such as Quebec (Law 25) and California.
     */
    private $region_code = null;
    
    /**
     * User's detected region (privacy law jurisdiction)
     */
    private $region = null;
    
    /**
     * EEA country codes (GDPR / ePrivacy Directive — strict opt-in)
     *
     * Includes all 27 EU Member States plus the three EEA non-EU members
     * (Iceland, Liechtenstein, Norway), which apply GDPR via the EEA Agreement.
     */
    private $eu_countries = array(
        // EU Member States
        'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
        'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
        'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
        // EEA non-EU members (apply GDPR via EEA Agreement)
        'IS', 'LI', 'NO',
    );
    
    /**
     * UK country codes (UK GDPR + DUAA 2025 — separate regime from EU since Feb 2026)
     */
    private $uk_countries = array(
        'GB', 'UK',
    );
    
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
        // Don't detect in constructor - wait for get_region() to be called
        // This ensures fresh detection per request, not cached in singleton
    }
    
    /**
     * Detect user's location
     */
    public function detect_location() {
        // Check if geolocation is enabled (constant or option)
        $geo_enabled = defined('MBR_CC_FORCE_GEOLOCATION') && MBR_CC_FORCE_GEOLOCATION;
        if (!$geo_enabled) {
            $geo_enabled = get_option('mbr_cc_geolocation_enabled', false);
        }
        
        if (!$geo_enabled) {
            $this->region = 'default';
            return;
        }
        
        // Check cache first
        $cached = $this->get_cached_location();
        if ($cached) {
            $ip = $this->get_user_ip();
            $this->country_code = $cached['country'];
            $this->region_code  = isset($cached['region_code']) ? $cached['region_code'] : null;
            $this->region = $cached['region'];
            return;
        }
        
        // Get user IP
        $ip = $this->get_user_ip();
        
        // Detect country (and optional sub-national region) from IP
        $detection = $this->detect_country_from_ip($ip);
        $was_detected = true;
        if (is_array($detection)) {
            $this->country_code = isset($detection['country']) ? $detection['country'] : null;
            $this->region_code  = isset($detection['region_code']) ? $detection['region_code'] : null;
            $was_detected       = !isset($detection['detected']) || $detection['detected'];
        } else {
            // Backwards-compatible scalar return.
            $this->country_code = $detection;
            $this->region_code  = null;
        }
        
        // Determine privacy region
        $this->region = $this->determine_region($this->country_code, $this->region_code);
        
        // Cache the result. Genuine provider answers are cached for the full
        // configured duration; fallbacks only briefly, so a provider hiccup
        // self-heals within minutes instead of persisting for a day.
        $this->cache_location($this->country_code, $this->region, $this->region_code, $was_detected);
    }
    
    /**
     * Get user's IP address
     */
    private function get_user_ip() {
        // Proxy headers are only honoured when the request demonstrably came
        // through a proxy we trust, so a visitor can no longer nominate their
        // own IP — and therefore their own privacy regime — with a forged
        // X-Forwarded-For. See includes/mbr-cc-ip.php.
        if (function_exists('mbr_cc_get_client_ip')) {
            return mbr_cc_get_client_ip();
        }

        return isset($_SERVER['REMOTE_ADDR']) && filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP)
            ? $_SERVER['REMOTE_ADDR']
            : '';
    }
    
    /**
     * Detect country (and where available, sub-national region) from IP address.
     *
     * Returns either:
     *   string  - country code (legacy callers that don't need region)
     *   array   - array('country' => 'XX', 'region_code' => 'XX')
     *
     * The array form is returned when a provider supplies sub-national region data.
     * Sub-national region codes are required for jurisdictions like Quebec (Law 25)
     * that differ materially from the country-level regime.
     */
    private function detect_country_from_ip($ip) {
        
        // Allow manual override for testing. Marked as not-detected so it is
        // only cached briefly and clears itself quickly when the constant is
        // removed.
        if (defined('MBR_CC_TEST_COUNTRY')) {
            $result = array('country' => MBR_CC_TEST_COUNTRY, 'region_code' => null, 'detected' => false);
            if (defined('MBR_CC_TEST_REGION')) {
                $result['region_code'] = MBR_CC_TEST_REGION;
            }
            return $result;
        }
        
        // Skip local/private/reserved IPs. String prefix matching missed the
        // 172.16.0.0/12 block and every IPv6 private range, so a LAN visitor
        // could trigger a pointless outbound lookup on every request.
        if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return array('country' => $this->get_default_country(), 'region_code' => null, 'detected' => false);
        }

        // Bound outbound lookups. Even with correct IP resolution, a burst of
        // real traffic with cold caches can stack up blocking HTTP calls and
        // trip the provider's own rate limit; when that happens every caller
        // gets the default region instead of waiting on a doomed request.
        if (!$this->can_make_lookup()) {
            return array('country' => $this->get_default_country(), 'region_code' => null, 'detected' => false);
        }
        
        // Get API provider
        $provider = get_option('mbr_cc_geolocation_provider', 'ip-api');
        
        $detected = false;
        
        switch ($provider) {
            case 'ip-api':
                $detected = $this->detect_via_ipapi($ip);
                break;
            case 'ipapi':
                $detected = $this->detect_via_ipapi_com($ip);
                break;
            case 'cloudflare':
                $detected = $this->detect_via_cloudflare();
                break;
            default:
                $detected = $this->detect_via_ipapi($ip);
        }
        
        // Normalise return value to array form.
        if (is_string($detected) && $detected !== '') {
            $detected = array('country' => $detected, 'region_code' => null);
        }
        
        // Fallback to default if detection fails.
        //
        // The 'detected' flag records whether this is a genuine provider
        // answer or a fallback. Fallbacks (provider down, rate-limited, or
        // outbound blocked) must NOT be cached for the full duration -
        // caching a failure for 24 hours pins the wrong region for that
        // visitor's IP all day, and if a page cache primes during that
        // window, for every visitor to that page.
        if (!is_array($detected) || empty($detected['country'])) {
            $detected = array('country' => $this->get_default_country(), 'region_code' => null, 'detected' => false);
        } elseif (!isset($detected['detected'])) {
            $detected['detected'] = true;
        }
        
        $detected['country'] = strtoupper($detected['country']);
        if (!empty($detected['region_code'])) {
            $detected['region_code'] = strtoupper($detected['region_code']);
        }
        
        return $detected;
    }
    
    /**
     * Detect via ip-api.com (Free, 45 req/min)
     *
     * Fetches both country code and sub-national region code so that
     * province/state-level rules (e.g. Quebec Law 25) can be applied correctly.
     */
    private function detect_via_ipapi($ip) {
        // ip-api.com serves HTTPS only on its paid endpoint. Over plain HTTP an
        // on-path attacker can rewrite countryCode and silently downgrade a
        // visitor's privacy regime, so HTTPS is used when a key is configured
        // and the plaintext endpoint requires an explicit opt-in.
        $api_key = trim((string) get_option('mbr_cc_ipapi_key', ''));

        if ($api_key !== '') {
            $url = add_query_arg(
                array(
                    'fields' => 'countryCode,region',
                    'key'    => $api_key,
                ),
                'https://pro.ip-api.com/json/' . rawurlencode($ip)
            );
        } else {
            if (!get_option('mbr_cc_allow_insecure_geo_lookup', false)) {
                return false;
            }

            $url = add_query_arg(
                array('fields' => 'countryCode,region'),
                'http://ip-api.com/json/' . rawurlencode($ip)
            );
        }

        $response = wp_remote_get($url, array(
            'timeout' => 5,
        ));

        if (is_wp_error($response)) {
            return false;
        }

        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if (!is_array($data) || empty($data['countryCode'])) {
            return false;
        }

        return array(
            'country'     => $this->sanitize_country_code($data['countryCode']),
            'region_code' => isset($data['region']) ? $this->sanitize_region_code($data['region']) : null,
        );
    }
    
    /**
     * Detect via ipapi.co (Free, 1000 req/day)
     *
     * Uses the JSON endpoint so we can extract both country and region code
     * in a single request.
     */
    private function detect_via_ipapi_com($ip) {
        $response = wp_remote_get('https://ipapi.co/' . rawurlencode($ip) . '/json/', array(
            'timeout' => 5,
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        if ((int) wp_remote_retrieve_response_code($response) !== 200) {
            return false;
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!is_array($data)) {
            return false;
        }
        
        // ipapi.co signals quota exhaustion with a 200 and an error body.
        if (!empty($data['error'])) {
            return false;
        }
        
        $country = isset($data['country_code']) ? $this->sanitize_country_code($data['country_code']) : '';
        
        if ($country === '') {
            return false;
        }
        
        return array(
            'country'     => $country,
            'region_code' => isset($data['region_code']) ? $this->sanitize_region_code($data['region_code']) : null,
        );
    }
    
    /**
     * Detect via Cloudflare headers
     *
     * Cloudflare's standard plan exposes only CF-IPCountry. Region codes are
     * available on Enterprise plans via the CF-Region-Code header — when present
     * we'll use it, otherwise we fall back to country-only detection.
     */
    private function detect_via_cloudflare() {
        // Cloudflare adds CF-IPCountry header
        if (empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
            return false;
        }
        
        // Only trust the header when the request actually arrived from a
        // Cloudflare edge address. Otherwise any visitor could set it and pick
        // their own privacy regime.
        if (function_exists('mbr_cc_ip_in_ranges') && function_exists('mbr_cc_cloudflare_ranges')) {
            $remote = isset($_SERVER['REMOTE_ADDR']) ? trim(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
            
            if (!filter_var($remote, FILTER_VALIDATE_IP) || !mbr_cc_ip_in_ranges($remote, mbr_cc_cloudflare_ranges())) {
                return false;
            }
        }
        
        $country = $this->sanitize_country_code(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']));
        
        // Cloudflare sends XX for unknown and T1 for Tor exit nodes.
        if ($country === '' || $country === 'XX' || $country === 'T1') {
            return false;
        }
        
        $result = array(
            'country'     => $country,
            'region_code' => null,
        );
        
        // Enterprise plans expose region as CF-Region-Code (e.g. "QC", "CA").
        if (!empty($_SERVER['HTTP_CF_REGION_CODE'])) {
            $region = $this->sanitize_region_code(wp_unslash($_SERVER['HTTP_CF_REGION_CODE']));
            
            if ($region !== '') {
                $result['region_code'] = $region;
            }
        }
        
        return $result;
    }
    
    /**
     * Normalise a country code coming from a provider or upstream header.
     *
     * Provider responses and proxy headers are external input. They are used to
     * pick a legal regime and are cached, so they are constrained to the shape
     * of an ISO 3166-1 alpha-2 code before going anywhere near that decision.
     *
     * @param mixed $code Raw value.
     * @return string Two-letter uppercase code, or '' if not valid.
     */
    private function sanitize_country_code($code) {
        if (!is_scalar($code)) {
            return '';
        }
        
        $code = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $code));
        
        return strlen($code) === 2 ? $code : '';
    }
    
    /**
     * Normalise a sub-national region code coming from a provider or header.
     *
     * ISO 3166-2 subdivision codes are alphanumeric and short; providers vary
     * between "QC" and longer forms, so length is capped rather than fixed.
     *
     * @param mixed $code Raw value.
     * @return string Sanitised code, or '' if not usable.
     */
    private function sanitize_region_code($code) {
        if (!is_scalar($code)) {
            return '';
        }
        
        $code = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $code));
        
        return strlen($code) <= 10 ? $code : '';
    }
    
    /**
     * Rate-limit outbound geolocation lookups.
     *
     * Providers rate-limit by origin IP, so on shared hosting the whole account
     * shares a budget. Exceeding it makes every lookup fail, which is worse
     * than deliberately falling back to the default region for the overflow.
     *
     * Counter is per-minute and best-effort: a lost race costs at most a few
     * extra requests, which is well inside the provider's tolerance.
     *
     * @return bool True if a lookup may be made.
     */
    private function can_make_lookup() {
        /**
         * Filter the maximum number of outbound geolocation lookups per minute.
         *
         * Defaults to 40, just under ip-api.com's documented free limit of 45.
         *
         * @since 2.3.1
         *
         * @param int $limit Maximum lookups per minute. 0 disables the limit.
         */
        $limit = (int) apply_filters('mbr_cc_geolocation_lookups_per_minute', 40);
        
        if ($limit <= 0) {
            return true;
        }
        
        $key   = 'mbr_cc_geo_rate_' . gmdate('YmdHi');
        $count = (int) get_transient($key);
        
        if ($count >= $limit) {
            return false;
        }
        
        // Two-minute expiry so the row cannot outlive its usefulness even if
        // the clock or cache behaves oddly.
        set_transient($key, $count + 1, 120);
        
        return true;
    }
    
    /**
     * Determine privacy region from country code (and optional sub-national region).
     *
     * @param string      $country_code ISO 3166-1 alpha-2 country code.
     * @param string|null $region_code  Optional ISO 3166-2 region/state/province code.
     * @return string Region key understood by MBR_CC_Region_Config.
     */
    private function determine_region($country_code, $region_code = null) {
        // UK — UK GDPR + DUAA 2025 (separate from EU since Feb 2026)
        if (in_array($country_code, $this->uk_countries)) {
            return 'uk_duaa';
        }
        
        // EEA — GDPR / ePrivacy Directive (strict opt-in).
        // Includes EU-27 plus EEA non-EU members IS, LI, NO.
        if (in_array($country_code, $this->eu_countries)) {
            return 'eu_gdpr';
        }
        
        // Switzerland — Federal Act on Data Protection (revFADP / nFADP) effective 1 Sept 2023.
        // GDPR-equivalent in substance; treated separately to keep messaging accurate.
        if ($country_code === 'CH') {
            return 'ch_nfadp';
        }
        
        // United States — multi-state privacy laws + GPC (20 states by 2026)
        if ($country_code === 'US') {
            return 'us_multi';
        }
        
        // Canada — Quebec Law 25 takes precedence over PIPEDA where region is detected.
        // Law 25 requires express opt-in for non-essential cookies and is materially
        // stricter than PIPEDA, so visitors confirmed to be in Quebec get the stricter regime.
        if ($country_code === 'CA') {
            if ($region_code === 'QC') {
                return 'ca_quebec';
            }
            return 'pipeda';
        }
        
        // Australia — Privacy Act 1988 + Privacy and Other Legislation Amendment Act 2024.
        // APP-based regime; informed consent needed where cookies collect personal information.
        if ($country_code === 'AU') {
            return 'au_privacy';
        }
        
        // Brazil — LGPD
        if ($country_code === 'BR') {
            return 'lgpd';
        }
        
        // India — Digital Personal Data Protection Act 2023 (Rules notified Nov 2025)
        if ($country_code === 'IN') {
            return 'india_dpdp';
        }
        
        // Vietnam — Personal Data Protection Law (PDPL, Law 91/2025/QH15) in force
        // 1 January 2026, with Decree 356/2025/ND-CP. Consent-centric and GDPR-like:
        // consent must be voluntary, specific, informed, granular per purpose, and
        // easily withdrawable, with silence explicitly NOT constituting consent.
        // Applies extraterritorially to any entity processing Vietnamese residents'
        // data, so visitors detected in Vietnam get an opt-in banner.
        if ($country_code === 'VN') {
            return 'vn_pdpl';
        }
        
        // Indonesia — Personal Data Protection Law (UU PDP, Law No. 27 of 2022),
        // fully effective 17 October 2024 and upheld by the Constitutional Court
        // in January 2026. GDPR-style: explicit, purpose-specific, withdrawable
        // consent. Applies extraterritorially to processors of Indonesian
        // residents' data, so visitors detected in Indonesia get an opt-in banner.
        if ($country_code === 'ID') {
            return 'id_pdp';
        }
        
        // Nigeria — NDPA 2023 + the NDPC's General Application and
        // Implementation Directive (GAID), effective 19 September 2025. The
        // GAID explicitly requires a prominent homepage cookie notice with a
        // genuine accept/decline choice and no implied consent from browsing.
        if ($country_code === 'NG') {
            return 'ng_ndpa';
        }
        
        // China — PIPL. Explicit opt-in for identifying cookies, plus separate
        // consent for sensitive data and cross-border transfers (the latter is
        // NOT handled by this plugin — see the region config docblock).
        if ($country_code === 'CN') {
            return 'cn_pipl';
        }
        
        // South Korea — PIPA. Specific, informed, prior consent wherever cookie
        // data can identify a person; notice-then-opt-out is insufficient.
        if ($country_code === 'KR') {
            return 'kr_pipa';
        }
        
        // Saudi Arabia — PDPL. Consent is the default lawful basis, and SDAIA
        // enforcement has been active since 2025.
        if ($country_code === 'SA') {
            return 'sa_pdpl';
        }
        
        // South Africa — POPIA. Section 69 requires opt-in for electronic
        // direct marketing, which covers most remarketing cookie use.
        if ($country_code === 'ZA') {
            return 'za_popia';
        }
        
        // Default for rest of world.
        // NOTE: as of 2.3.0 this is an opt-in posture for NEW installs — see
        // MBR_CC_Region_Config::get_default_config(). Countries that genuinely
        // require opt-in but are not mapped above (e.g. UAE, Thailand) are the
        // reason. Japan is knowingly over-served: notice or opt-out is
        // generally sufficient there under the APPI and the Telecommunications
        // Business Act external transmission rules.
        return 'default';
    }
    
    /**
     * Get default country if detection fails
     */
    private function get_default_country() {
        return get_option('mbr_cc_geolocation_default', 'US');
    }
    
    /**
     * Cache location data
     *
     * Genuine provider answers are cached for the configured duration
     * (default 24 hours). Fallback answers - produced when the provider is
     * unreachable, rate-limited, or returns nothing - are cached for a much
     * shorter window (default 5 minutes, filterable via
     * 'mbr_cc_geolocation_failure_cache') so that a transient failure
     * self-heals quickly rather than pinning a possibly-wrong region to the
     * visitor's IP for a full day.
     *
     * @param string      $country      ISO 3166-1 alpha-2 country code.
     * @param string      $region       Resolved privacy region key.
     * @param string|null $region_code  Optional ISO 3166-2 sub-national region code.
     * @param bool        $was_detected Whether this is a genuine provider answer.
     */
    private function cache_location($country, $region, $region_code = null, $was_detected = true) {
        $ip = $this->get_user_ip();
        if (empty($ip)) {
            return;
        }
        
        if ($was_detected) {
            $cache_duration = get_option('mbr_cc_geolocation_cache', 86400); // 24 hours default
        } else {
            /**
             * Filter the cache duration for failed/fallback geolocation lookups.
             *
             * @param int $duration Seconds to cache a fallback result. Default 300.
             */
            $cache_duration = (int) apply_filters('mbr_cc_geolocation_failure_cache', 300);
        }
        
        set_transient(
            'mbr_cc_geo_' . md5($ip),
            array(
                'country'     => $country,
                'region'      => $region,
                'region_code' => $region_code,
                'detected'    => (bool) $was_detected,
                'timestamp'   => time(),
            ),
            $cache_duration
        );
    }
    
    /**
     * Get cached location data
     */
    private function get_cached_location() {
        $ip = $this->get_user_ip();
        if (empty($ip)) {
            return false;
        }
        
        return get_transient('mbr_cc_geo_' . md5($ip));
    }
    
    /**
     * Get detected country code
     */
    public function get_country() {
        if ($this->country_code === null) {
            $this->detect_location();
        }
        return $this->country_code;
    }
    
    /**
     * Get detected sub-national region code (e.g. ISO 3166-2 region for Canada/US).
     *
     * Returns null if the configured provider doesn't supply region data or the
     * visitor's location couldn't be resolved to a sub-national region.
     *
     * @return string|null
     */
    public function get_region_code() {
        if ($this->country_code === null) {
            $this->detect_location();
        }
        return $this->region_code;
    }
    
    /**
     * Get detected region
     */
    public function get_region() {
        // Always detect fresh for each request (don't trust cached object property)
        $this->detect_location();
        return $this->region;
    }
    
    /**
     * Check if user is in EU/EEA (GDPR strict opt-in)
     */
    public function is_eu() {
        return $this->get_region() === 'eu_gdpr';
    }
    
    /**
     * Check if user is in UK (DUAA 2025 regime)
     */
    public function is_uk() {
        return $this->get_region() === 'uk_duaa';
    }
    
    /**
     * Check if user is in EU/EEA or UK (either GDPR-derived regime)
     * Backwards-compatible helper.
     */
    public function is_eu_uk() {
        return in_array($this->get_region(), array('eu_gdpr', 'uk_duaa'));
    }
    
    /**
     * Check if user is in US multi-state region
     */
    public function is_us() {
        return $this->get_region() === 'us_multi';
    }
    
    /**
     * Check if user is in California/CCPA region
     * Backwards-compatible alias — now maps to the broader US multi-state region.
     */
    public function is_ccpa() {
        return $this->get_region() === 'us_multi';
    }
    
    /**
     * Check if user is in Brazil
     */
    public function is_lgpd() {
        return $this->get_region() === 'lgpd';
    }
    
    /**
     * Check if user is in India
     */
    public function is_dpdp() {
        return $this->get_region() === 'india_dpdp';
    }
    
    /**
     * Check if user is in Quebec (Law 25 regime)
     */
    public function is_quebec() {
        return $this->get_region() === 'ca_quebec';
    }
    
    /**
     * Check if user is in Switzerland (revFADP / nFADP)
     */
    public function is_switzerland() {
        return $this->get_region() === 'ch_nfadp';
    }
    
    /**
     * Check if user is in Australia (Privacy Act 1988 as amended)
     */
    public function is_australia() {
        return $this->get_region() === 'au_privacy';
    }
    
    /**
     * Get region display name
     */
    /**
     * Get region display name.
     *
     * @param string|null $region Optional region key. Defaults to the detected
     *                            region. Passing an explicit key lets admin
     *                            tooling label an arbitrary region without
     *                            duplicating this table.
     * @return string
     */
    public function get_region_name($region = null) {
        $names = array(
            'eu_gdpr'    => 'EU/EEA (GDPR / ePrivacy Directive)',
            'uk_duaa'    => 'United Kingdom (UK GDPR + DUAA 2025)',
            'us_multi'   => 'United States (CCPA + 20 State Laws in effect / GPC)',
            'ca_quebec'  => 'Canada — Quebec (Law 25)',
            'pipeda'     => 'Canada (PIPEDA / CASL)',
            'ch_nfadp'   => 'Switzerland (revFADP / nFADP)',
            'au_privacy' => 'Australia (Privacy Act 1988, as amended)',
            'lgpd'       => 'Brazil (LGPD)',
            'india_dpdp' => 'India (DPDP Act 2023, Rules 2025)',
            'vn_pdpl'    => 'Vietnam (PDPL, Law 91/2025 — in force 1 Jan 2026)',
            'id_pdp'     => 'Indonesia (UU PDP, Law 27/2022)',
            'ng_ndpa'    => 'Nigeria (NDPA 2023 + GAID, effective 19 Sep 2025)',
            'cn_pipl'    => 'China (PIPL)',
            'kr_pipa'    => 'South Korea (PIPA)',
            'sa_pdpl'    => 'Saudi Arabia (PDPL)',
            'za_popia'   => 'South Africa (POPIA)',
            'default'    => 'Rest of World (safe default — opt-in)',
            // Legacy keys for backwards compatibility with cached transients.
            'eu_uk'      => 'EU/UK (GDPR)',
            'ccpa'       => 'United States (CCPA)',
        );
        
        if ($region === null) {
            $region = $this->get_region();
        }
        return isset($names[$region]) ? $names[$region] : $names['default'];
    }
    
    /**
     * Clear location cache
     */
    public function clear_cache() {
        $ip = $this->get_user_ip();
        if (!empty($ip)) {
            delete_transient('mbr_cc_geo_' . md5($ip));
        }
    }
}

// Initialize on plugins_loaded
add_action('plugins_loaded', function() {
    MBR_CC_Geolocation::get_instance();
}, 5);

// Helper function to get instance
function mbr_cc_geolocation() {
    return MBR_CC_Geolocation::get_instance();
}
