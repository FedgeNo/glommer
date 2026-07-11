<?php

declare(strict_types=1);

class URL
{
    private static ?string $siteURL = null;

    public static function absolute(string $path): string
    {
        if (self::$siteURL === null) {
            $config = require __DIR__ . '/../config.php';
            self::$siteURL = rtrim($config['siteURL'], '/');
        }

        return self::$siteURL . $path;
    }

    /**
     * Whether a user-supplied link is a public http(s) URL safe to post -
     * rejecting localhost and anything on a private or reserved IP range,
     * whether given as a literal IP or as a hostname that resolves to one.
     * The link-preview fetch (SafeHTTPFetcher) already refuses these; this is
     * the same guard at post time, so such a link can't even be stored or
     * shown as a clickable href.
     */
    public static function isPublicHTTP(string $url): bool
    {
        $parts = parse_url($url);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (!in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        // parse_url keeps the brackets on an IPv6 literal host ([::1]); strip
        // them so the IP check below sees a bare address (and rejects it -
        // isPublicIP only accepts IPv4).
        $host = trim($parts['host'], '[]');

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIP($host);
        }

        $lower = strtolower($host);

        if ($lower === 'localhost' || str_ends_with($lower, '.localhost') || str_ends_with($lower, '.local')) {
            return false;
        }

        // A hostname that resolves to a private/reserved address is just a
        // dressed-up internal link - block it. Only A (IPv4) records are
        // considered: we don't fetch over IPv6 at all (see SafeHTTPFetcher),
        // which sidesteps the IPv6 transition-range address tricks that slip an
        // internal IPv4 past the private/reserved filter. A name that doesn't
        // resolve to any IPv4 is left to pass (a transient DNS miss shouldn't
        // reject a real link, and the preview fetch re-checks the resolved IP).
        $records = @dns_get_record($host, DNS_A);

        if ($records === false || $records === []) {
            return true;
        }

        foreach ($records as $record) {
            $ip = $record['ip'] ?? null;

            if ($ip !== null && !self::isPublicIP($ip)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicIP(string $ip): bool
    {
        // IPv4 only - an IPv6 address (literal or resolved) never validates here.
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }
}
