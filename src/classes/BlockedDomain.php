<?php

declare(strict_types=1);

/**
 * Whole servers this instance refuses to deal with.
 *
 * Blocking accounts one at a time does not work against a server that mints
 * them faster than a moderator can act, which is the usual shape of federated
 * abuse. Blocking the server closes the whole tap.
 *
 * Matching includes subdomains, because a host that hands out
 * anything.badserver.example is the same problem as badserver.example itself.
 * The port is dropped and the name lowercased before anything is compared -
 * a hostname is case-insensitive, and letting BadServer.example through while
 * badserver.example is blocked would make the whole thing decorative.
 */
class BlockedDomain
{
    /** Adds a domain. Silently does nothing for input that is not a hostname. */
    public static function block(string $domain, ?string $reason, ?int $moderator_id): void
    {
        $normalized = self::normalize($domain);

        if ($normalized === null) {
            return;
        }

        DB::run('
INSERT INTO `BlockedDomains` (`domain`, `reason`, `blockedBy`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`), `blockedBy` = VALUES(`blockedBy`)
', 'ssi', $normalized, $reason, $moderator_id);
    }

    public static function unblock(string $domain): void
    {
        $normalized = self::normalize($domain);

        if ($normalized === null) {
            return;
        }

        DB::run('
DELETE FROM `BlockedDomains`
    WHERE `domain` = ?
', 's', $normalized);
    }

    /**
     * Whether a URL belongs to a blocked server. Takes the whole URL rather
     * than a host so no caller has to remember to extract one - the place that
     * forgets is the place that lets a delivery through.
     */
    public static function blocksURL(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && self::blocks($host);
    }

    /** Whether a hostname is blocked, itself or as a subdomain of one. */
    public static function blocks(string $domain): bool
    {
        $normalized = self::normalize($domain);

        if ($normalized === null) {
            return false;
        }

        // Every suffix that could be the blocked name: a.b.example is blocked
        // by a.b.example, by b.example, or by example. Checked as a set rather
        // than with a LIKE, so nothing depends on pattern escaping.
        $candidates = [];
        $labels = explode('.', $normalized);

        for ($index = 0; $index < count($labels) - 1; $index++) {
            $candidates[] = implode('.', array_slice($labels, $index));
        }

        if ($candidates === []) {
            return false;
        }

        $placeholders = implode(', ', array_fill(0, count($candidates), '?'));

        return DB::row('
SELECT `domain`
    FROM `BlockedDomains`
    WHERE `domain` IN (' . $placeholders . ')
', 'BlockedDomainData', str_repeat('s', count($candidates)), ...$candidates) !== null;
    }

    /** @return BlockedDomainData[] */
    public static function all(): array
    {
        return DB::rows('
SELECT *
    FROM `BlockedDomains`
    ORDER BY `domain`
', 'BlockedDomainData');
    }

    /**
     * A hostname reduced to what is compared: lowercased, port removed, no
     * scheme or path. Null for anything that is not shaped like a hostname, so
     * a blank or malformed entry can never become a rule that matches
     * everything.
     *
     * Deliberately a structural check rather than URL::isValidHostname, which
     * also demands a real IANA TLD. That is right when resolving a handle
     * someone typed - a made-up TLD there is a mistake - but wrong here: a
     * moderator blocking a server should get exactly what they asked for, and a
     * blocklist entry that matches nothing costs nothing. Requiring a known TLD
     * would instead mean a host this list cannot express, which is the one
     * failure mode a blocklist must not have.
     */
    private static function normalize(string $domain): ?string
    {
        $domain = strtolower(trim($domain));

        // Accept a pasted URL as well as a bare host - a moderator reaching for
        // this has usually just copied an address.
        if (str_contains($domain, '://')) {
            $domain = (string) parse_url($domain, PHP_URL_HOST);
        }

        $domain = explode(':', $domain)[0];
        $domain = rtrim($domain, '.');

        if ($domain === '' || strlen($domain) > 253) {
            return null;
        }

        // At least two labels, each of letters, digits and inner hyphens. That
        // rules out a bare word (which would be a single-label rule matching a
        // whole TLD by accident) without ruling out a real host.
        if (!preg_match('/\A[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+\z/', $domain)) {
            return null;
        }

        return $domain;
    }
}
