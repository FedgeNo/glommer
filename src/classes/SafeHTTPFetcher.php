<?php

declare(strict_types=1);

/**
 * A GET client for fetching resources at user-submitted URLs (link previews,
 * their OG images) without exposing the server to SSRF. Three things make
 * that safe, all necessary together:
 *
 *  1. Every hostname is resolved to an IP address ourselves, and that IP is
 *     checked against the private/reserved ranges (loopback, RFC1918, link
 *     local - which also covers the 169.254.169.254 cloud metadata address)
 *     before any connection is attempted.
 *  2. curl is pinned to that exact validated IP via CURLOPT_RESOLVE, so it
 *     can never re-resolve the hostname itself and connect somewhere else -
 *     otherwise a DNS response that changes between our check and curl's own
 *     lookup (or simply differs for an IPv6 vs IPv4 query) would silently
 *     defeat the check.
 *  3. Redirects are never auto-followed. Each Location header is fed back
 *     through the exact same validate-then-pin process, so a redirect to an
 *     internal address is caught exactly like a direct request would be.
 */
class SafeHTTPFetcher
{
    private const MAX_REDIRECTS = 3;
    private const CONNECT_TIMEOUT_SECONDS = 3;
    private const TOTAL_TIMEOUT_SECONDS = 5;

    // Some sites serve stripped-down (or no) OG/JSON-LD markup to requests
    // that identify as a bot, so this presents as an ordinary Firefox visit
    // rather than naming itself - the site content pulled here is only ever
    // shown back to the user who's about to post the link, and only after
    // GD has re-encoded any image, so there's no cloaking/deception concern
    // on our side, just avoiding being met with a bot-tier response.
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:141.0) Gecko/20100101 Firefox/141.0';
    private const REQUEST_HEADERS = [
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.5',
        'Upgrade-Insecure-Requests: 1',
        'Sec-Fetch-Dest: document',
        'Sec-Fetch-Mode: navigate',
        'Sec-Fetch-Site: none',
        'Sec-Fetch-User: ?1',
    ];

    /**
     * @return array{body: string, contentType: ?string}|null
     */
    public static function get(string $url, int $max_bytes): ?array
    {
        // The browser-shaped identity and the Range hint are what make this a
        // link-preview fetch rather than an API call; everything protective
        // about the request is shared with every other caller below.
        return self::sendRequest('GET', $url, self::REQUEST_HEADERS, null, $max_bytes, self::MAX_REDIRECTS, [
            CURLOPT_USERAGENT => self::USER_AGENT,
            CURLOPT_RANGE => '0-' . $max_bytes,
        ]);
    }

    /**
     * A GET with caller-chosen headers instead of the browser-shaped ones
     * get() sends - for ActivityPub/WebFinger fetches, which need to identify
     * as themselves (Accept: application/activity+json) rather than pretend
     * to be a browser. Same SSRF protections as get(): resolve-then-pin,
     * IPv4-only, standard ports only, redirects re-validated one by one.
     *
     * @param string[] $headers
     * @return array{body: string, contentType: ?string}|null
     */
    public static function getJSON(string $url, array $headers, int $max_bytes): ?array
    {
        return self::sendRequest('GET', $url, $headers, null, $max_bytes, self::MAX_REDIRECTS);
    }

    /**
     * A POST with caller-chosen headers and a raw body - for delivering a
     * signed ActivityPub activity to a remote inbox. Never follows a
     * redirect: replaying a signed POST at a second, server-chosen URL is a
     * meaningfully different request than the caller signed for, so this
     * fails closed instead.
     *
     * @param string[] $headers
     * @return array{body: string, contentType: ?string}|null
     */
    public static function postJSON(string $url, string $body, array $headers, int $max_bytes): ?array
    {
        return self::sendRequest('POST', $url, $headers, $body, $max_bytes, 0);
    }

    /**
     * The one implementation of "make an outbound request safely". Every
     * caller goes through here so the SSRF protections - resolve the host
     * ourselves, pin curl to that validated IP, IPv4 only, standard ports
     * only, never auto-follow a redirect - can't drift apart between them.
     *
     * @param string[] $headers
     * @param array<int, mixed> $extra_options
     * @return array{body: string, contentType: ?string}|null
     */
    private static function sendRequest(string $method, string $url, array $headers, ?string $body, int $max_bytes, int $redirects_left, array $extra_options = []): ?array
    {
        $parts = parse_url($url);

        if (
            $parts === false
            || !isset($parts['host'])
            || $parts['host'] === ''
            || !in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
        ) {
            return null;
        }

        $ip = self::resolveAndValidate($parts['host']);

        if ($ip === null) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);

        if (!in_array($port, [80, 443], true)) {
            return null;
        }

        $downloaded = '';
        $exceeded_cap = false;

        $curl = curl_init();
        $options = [
            CURLOPT_URL => $url,
            CURLOPT_RESOLVE => [$parts['host'] . ':' . $port . ':' . $ip],
            CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
            CURLOPT_HEADER => false,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT_SECONDS,
            CURLOPT_TIMEOUT => self::TOTAL_TIMEOUT_SECONDS,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_WRITEFUNCTION => function ($handle, string $chunk) use (&$downloaded, &$exceeded_cap, $max_bytes) {
                $downloaded .= $chunk;

                if (strlen($downloaded) > $max_bytes) {
                    $exceeded_cap = true;

                    return -1;
                }

                return strlen($chunk);
            },
        ];

        if ($method === 'POST') {
            $options[CURLOPT_POST] = true;
            $options[CURLOPT_POSTFIELDS] = $body ?? '';
        }

        curl_setopt_array($curl, $options + $extra_options);

        $success = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $content_type = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        $redirect_url = curl_getinfo($curl, CURLINFO_REDIRECT_URL);
        curl_close($curl);

        if ($success === false && !$exceeded_cap) {
            return null;
        }

        $response_body = substr($downloaded, 0, $max_bytes);

        if ($status >= 300 && $status < 400 && $redirect_url !== '' && $redirect_url !== null) {
            if ($redirects_left <= 0) {
                return null;
            }

            return self::sendRequest($method, $redirect_url, $headers, $body, $max_bytes, $redirects_left - 1, $extra_options);
        }

        if ($status < 200 || $status >= 300) {
            return null;
        }

        return [
            'body' => (string) $response_body,
            'contentType' => $content_type !== false ? $content_type : null,
        ];
    }

    private static function resolveAndValidate(string $host): ?string
    {
        // Only a real registrable hostname (a dotted name ending in an IANA
        // TLD) is fetchable - no bare IP (a literal fails this outright), no
        // localhost, no fake-TLD host - matching URL::isValidHTTPURL's post-time
        // rule. Applies to the initial URL, every redirect target, and a
        // page's OG image URL, since all three pass through here.
        if (!URL::isValidHostname($host)) {
            return null;
        }

        // A (IPv4) records only - we don't fetch over IPv6 at all. Beyond the
        // simplicity, this closes the IPv6 transition-range trick (NAT64/6to4/
        // Teredo/IPv4-mapped addresses that embed an internal IPv4 the private/
        // reserved filter doesn't decode). No IPv6, no bypass.
        $records = @dns_get_record($host, DNS_A);

        if ($records === false || $records === []) {
            return null;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? null;

            if ($ip !== null && self::isSafeIP($ip)) {
                return $ip;
            }
        }

        return null;
    }

    // FILTER_FLAG_NO_PRIV_RANGE/NO_RES_RANGE don't cover these - all three are
    // routable-looking enough that hosting providers and carriers use them for
    // internal infrastructure, so an SSRF that lands here still reaches
    // something private. Blocked explicitly since PHP's filter won't.
    private const EXTRA_BLOCKED_RANGES = [
        '100.64.0.0/10', // CGNAT (RFC 6598)
        '192.0.0.0/24', // IETF Protocol Assignments (RFC 6890)
        '198.18.0.0/15', // benchmarking (RFC 2544)
        '192.88.99.0/24', // 6to4 relay anycast (RFC 3068)
    ];

    private static function isSafeIP(string $ip): bool
    {
        // IPv4 only - an IPv6 address (literal or resolved) never validates here.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        foreach (self::EXTRA_BLOCKED_RANGES as $range) {
            if (self::ipInRange($ip, $range)) {
                return false;
            }
        }

        return true;
    }

    private static function ipInRange(string $ip, string $cidr): bool
    {
        [$subnet, $prefix_length] = explode('/', $cidr);

        $ip_long = ip2long($ip);
        $subnet_long = ip2long($subnet);
        $mask = -1 << (32 - (int) $prefix_length);

        return ($ip_long & $mask) === ($subnet_long & $mask);
    }
}
