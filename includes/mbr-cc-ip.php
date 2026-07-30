<?php
/**
 * Client IP resolution.
 *
 * Proxy headers (X-Forwarded-For, CF-Connecting-IP, X-Real-IP and friends) are
 * attacker-controlled by default: any visitor can send them, and unless the
 * request demonstrably arrived through a proxy we trust, they mean nothing.
 *
 * Trusting them blindly lets a visitor pick their own privacy regime, and lets
 * an unauthenticated attacker mint an unlimited number of distinct "IPs",
 * each of which creates its own geolocation transient and its own outbound
 * provider lookup.
 *
 * Resolution modes (option `mbr_cc_proxy_mode`):
 *
 *   'auto'       Default. REMOTE_ADDR is used unless the request genuinely
 *                arrived from a published Cloudflare range, in which case
 *                CF-Connecting-IP is honoured. Safe with no configuration:
 *                the range check, not the header, is the security control.
 *   'proxy'      For sites behind a non-Cloudflare reverse proxy or load
 *                balancer. X-Forwarded-For is honoured only when REMOTE_ADDR
 *                is a trusted proxy (see mbr_cc_trusted_proxies()).
 *   'none'       REMOTE_ADDR only. No proxy header is ever honoured.
 *
 * @package MBR_Cookie_Consent
 * @since   2.3.1
 */

// Exit if accessed directly.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Resolve the client IP address.
 *
 * @return string Validated IP address, or '' when none could be determined.
 */
function mbr_cc_get_client_ip() {
    $remote = isset($_SERVER['REMOTE_ADDR']) ? trim(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';

    if (!filter_var($remote, FILTER_VALIDATE_IP)) {
        return '';
    }

    $mode = mbr_cc_get_proxy_mode();

    if ($mode === 'none') {
        return $remote;
    }

    if ($mode === 'auto') {
        // Honour Cloudflare's header only when the connection actually came
        // from Cloudflare. Otherwise the header is forged and we ignore it.
        if (!mbr_cc_ip_in_ranges($remote, mbr_cc_cloudflare_ranges())) {
            return $remote;
        }

        if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $candidate = trim(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            if (filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return $remote;
    }

    // 'proxy' mode.
    $trusted = mbr_cc_trusted_proxies();

    if (!mbr_cc_ip_in_ranges($remote, $trusted)) {
        // The request did not come from a trusted proxy, so any forwarding
        // header on it was set by the client.
        return $remote;
    }

    $forwarded = '';
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $forwarded = wp_unslash($_SERVER['HTTP_X_FORWARDED_FOR']);
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $forwarded = wp_unslash($_SERVER['HTTP_X_REAL_IP']);
    }

    if ($forwarded === '') {
        return $remote;
    }

    // X-Forwarded-For is appended to left-to-right, so the rightmost entries
    // are the ones added by infrastructure we control. Walk right to left and
    // take the first address that is not itself a trusted proxy: everything
    // to the left of it was supplied by the client and cannot be trusted.
    $chain = array_map('trim', explode(',', $forwarded));

    for ($i = count($chain) - 1; $i >= 0; $i--) {
        $candidate = $chain[$i];

        if (!filter_var($candidate, FILTER_VALIDATE_IP)) {
            continue;
        }

        if (mbr_cc_ip_in_ranges($candidate, $trusted)) {
            continue;
        }

        return $candidate;
    }

    return $remote;
}

/**
 * Get the configured proxy trust mode.
 *
 * @return string One of 'auto', 'proxy', 'none'.
 */
function mbr_cc_get_proxy_mode() {
    $mode = get_option('mbr_cc_proxy_mode', 'auto');

    if (!in_array($mode, array('auto', 'proxy', 'none'), true)) {
        $mode = 'auto';
    }

    /**
     * Filter the proxy trust mode.
     *
     * @since 2.3.1
     *
     * @param string $mode One of 'auto', 'proxy', 'none'.
     */
    $mode = apply_filters('mbr_cc_proxy_mode', $mode);

    return in_array($mode, array('auto', 'proxy', 'none'), true) ? $mode : 'auto';
}

/**
 * CIDR ranges treated as trusted proxies in 'proxy' mode.
 *
 * Defaults to the private and loopback ranges, which covers the common case of
 * a reverse proxy on the same host or private network. Sites behind a proxy on
 * a public address must add it explicitly.
 *
 * @return array List of CIDR strings.
 */
function mbr_cc_trusted_proxies() {
    $configured = get_option('mbr_cc_trusted_proxies', '');

    $ranges = array();

    if (is_string($configured) && $configured !== '') {
        $ranges = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $configured)));
    } elseif (is_array($configured)) {
        $ranges = array_filter(array_map('trim', $configured));
    }

    if (empty($ranges)) {
        $ranges = array(
            '127.0.0.0/8',
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
            '169.254.0.0/16',
            '::1/128',
            'fc00::/7',
            'fe80::/10',
        );
    }

    /**
     * Filter the list of trusted proxy CIDR ranges.
     *
     * @since 2.3.1
     *
     * @param array $ranges List of CIDR strings.
     */
    return (array) apply_filters('mbr_cc_trusted_proxies', $ranges);
}

/**
 * Cloudflare's published edge ranges.
 *
 * Cloudflare updates these occasionally. They are filterable so a site can
 * refresh them from https://www.cloudflare.com/ips/ without waiting for a
 * plugin release.
 *
 * @return array List of CIDR strings.
 */
function mbr_cc_cloudflare_ranges() {
    $ranges = array(
        // IPv4.
        '173.245.48.0/20',
        '103.21.244.0/22',
        '103.22.200.0/22',
        '103.31.4.0/22',
        '141.101.64.0/18',
        '108.162.192.0/18',
        '190.93.240.0/20',
        '188.114.96.0/20',
        '197.234.240.0/22',
        '198.41.128.0/17',
        '162.158.0.0/15',
        '104.16.0.0/13',
        '104.24.0.0/14',
        '172.64.0.0/13',
        '131.0.72.0/22',
        // IPv6.
        '2400:cb00::/32',
        '2606:4700::/32',
        '2803:f800::/32',
        '2405:b500::/32',
        '2405:8100::/32',
        '2a06:98c0::/29',
        '2c0f:f248::/32',
    );

    /**
     * Filter the Cloudflare edge ranges used to validate CF-Connecting-IP.
     *
     * @since 2.3.1
     *
     * @param array $ranges List of CIDR strings.
     */
    return (array) apply_filters('mbr_cc_cloudflare_ranges', $ranges);
}

/**
 * Test whether an IP falls inside any of the given CIDR ranges.
 *
 * @param string $ip     IP address.
 * @param array  $ranges List of CIDR strings.
 * @return bool
 */
function mbr_cc_ip_in_ranges($ip, $ranges) {
    foreach ((array) $ranges as $range) {
        if (mbr_cc_ip_in_cidr($ip, $range)) {
            return true;
        }
    }

    return false;
}

/**
 * Test whether an IP falls inside a single CIDR range.
 *
 * Handles IPv4 and IPv6 through inet_pton(), comparing the network prefix
 * byte by byte. A bare address with no prefix is treated as a /32 or /128.
 *
 * @param string $ip   IP address.
 * @param string $cidr CIDR string, e.g. '192.168.0.0/16'.
 * @return bool
 */
function mbr_cc_ip_in_cidr($ip, $cidr) {
    if (!is_string($cidr) || $cidr === '') {
        return false;
    }

    if (strpos($cidr, '/') === false) {
        $subnet = $cidr;
        $bits   = null;
    } else {
        list($subnet, $bits) = explode('/', $cidr, 2);
        $bits = (int) $bits;
    }

    $ip_bin     = @inet_pton($ip);
    $subnet_bin = @inet_pton(trim($subnet));

    if ($ip_bin === false || $subnet_bin === false) {
        return false;
    }

    // Different address families never match.
    if (strlen($ip_bin) !== strlen($subnet_bin)) {
        return false;
    }

    $max_bits = strlen($ip_bin) * 8;

    if ($bits === null) {
        $bits = $max_bits;
    }

    if ($bits < 0 || $bits > $max_bits) {
        return false;
    }

    if ($bits === 0) {
        return true;
    }

    $whole_bytes = intdiv($bits, 8);
    $rem_bits    = $bits % 8;

    if ($whole_bytes > 0 && strncmp($ip_bin, $subnet_bin, $whole_bytes) !== 0) {
        return false;
    }

    if ($rem_bits > 0) {
        $mask = chr((0xFF << (8 - $rem_bits)) & 0xFF);

        if ((($ip_bin[$whole_bytes] ^ $subnet_bin[$whole_bytes]) & $mask) !== "\0") {
            return false;
        }
    }

    return true;
}

/**
 * Anonymise an IP address by zeroing the final segment.
 *
 * @param string $ip IP address.
 * @return string Anonymised IP address.
 */
function mbr_cc_anonymize_ip($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts    = explode('.', $ip);
        $parts[3] = '0';
        return implode('.', $parts);
    }

    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // Zero the final 80 bits, which is what the EDPB treats as adequate
        // truncation for IPv6 — dropping only the last group is not enough.
        $bin = @inet_pton($ip);

        if ($bin !== false) {
            $bin = substr($bin, 0, 6) . str_repeat("\0", 10);
            $out = @inet_ntop($bin);

            if ($out !== false) {
                return $out;
            }
        }

        return $ip;
    }

    return $ip;
}
