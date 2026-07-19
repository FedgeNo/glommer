<?php

declare(strict_types=1);

class URL
{
    /**
     * Whether a user-supplied link is a public http(s) URL safe to post. Only
     * a real registrable hostname - a dotted name ending in an IANA TLD (see
     * TLD) - is allowed: a bare IP (private OR public), localhost, and any
     * single-label or fake-TLD host are all refused, so a post can only ever
     * link to a named site, never an address. A named host that resolves to a
     * private/reserved IP is refused too (a dressed-up internal link). The
     * link-preview fetch (SafeHTTPFetcher) applies the same rules; this is the
     * guard at post time, so such a link can't even be stored or shown as a
     * clickable href.
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
        // them so isValidHostname sees the bare address and rejects it as an
        // IP, same as a bracketless IPv4 literal.
        $host = trim($parts['host'], '[]');

        // A real hostname ending in a known TLD, nothing else - this alone
        // rules out every IP literal, localhost, and single-label name.
        if (!self::isValidHostname($host)) {
            return false;
        }

        // A valid-looking name that resolves to a private/reserved address is
        // still an internal link - block it. Only A (IPv4) records are
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

    /**
     * Whether $host is a real registrable hostname: a dotted name whose final
     * label is an IANA TLD (see TLDList). A bare IP (private OR public),
     * "localhost", and any single-label or fake-TLD name are all false, so
     * only a named site validates - never an address. Compared uppercased
     * against the ASCII TLD list, so a punycode "xn--..." host validates while
     * a raw-unicode one does not (links are expected in ASCII/punycode form).
     */
    public static function isValidHostname(string $host): bool
    {
        $host = rtrim(strtolower(trim($host)), '.');

        if ($host === '' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        $last_dot = strrpos($host, '.');

        return $last_dot !== false && TLDList::exists(substr($host, $last_dot + 1));
    }
}
