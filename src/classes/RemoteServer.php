<?php

declare(strict_types=1);

/**
 * Another server, as this one deals with it. Today that means defederation;
 * anything else this instance comes to decide about a whole server belongs
 * here rather than in a class named for one answer.
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
class RemoteServer
{
    /**
     * Adds a domain and severs what is already established with it. Returns
     * the normalized name, or null for input that is not shaped like a host -
     * so a caller can tell a moderator their entry was rejected rather than
     * silently doing nothing.
     */
    public static function block(string $domain, ?string $reason, ?int $moderator_id): ?string
    {
        $normalized = self::normalize($domain);

        if ($normalized === null) {
            return null;
        }

        DB::run('
INSERT INTO `BlockedServers` (`domain`, `reason`, `blockedBy`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `reason` = VALUES(`reason`), `blockedBy` = VALUES(`blockedBy`)
', 'ssi', $normalized, $reason, $moderator_id);

        self::severFollows($normalized);

        return $normalized;
    }

    /**
     * Drops every follow in both directions with a newly blocked server.
     *
     * Blocking only future traffic would leave the relationships standing:
     * their followers here would sit waiting for posts that never come, and
     * members here would keep a follow of an account they can no longer
     * receive. Cutting them makes the block mean what it says.
     *
     * Matched on the host inside each actor URI rather than by joining on a
     * domain column, because there isn't one - the URI is the only place the
     * server is recorded.
     */
    private static function severFollows(string $domain): void
    {
        $suffix = '%.' . $domain . '/%';
        $exact = '%//' . $domain . '/%';

        DB::run('
DELETE FROM `FediverseFollowers`
    WHERE `remoteActorURI` LIKE ? OR `remoteActorURI` LIKE ?
', 'ss', $exact, $suffix);

        DB::run('
DELETE FROM `RemoteFollows`
    WHERE `remoteActorURI` LIKE ? OR `remoteActorURI` LIKE ?
', 'ss', $exact, $suffix);

        // Anything already queued for them is dropped too - it would only fail
        // its way through the retry schedule.
        DB::run('
DELETE FROM `FediverseDeliveries`
    WHERE `inboxURL` LIKE ? OR `inboxURL` LIKE ?
', 'ss', $exact, $suffix);
    }

    public static function unblock(string $domain): void
    {
        $normalized = self::normalize($domain);

        if ($normalized === null) {
            return;
        }

        DB::run('
DELETE FROM `BlockedServers`
    WHERE `domain` = ?
', 's', $normalized);
    }

    /**
     * Whether a URL belongs to a blocked server. Takes the whole URL rather
     * than a host so no caller has to remember to extract one - the place that
     * forgets is the place that lets a delivery through.
     */
    public static function isBlockedURL(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && self::isBlocked($host);
    }

    /** Whether a hostname is blocked, itself or as a subdomain of one. */
    public static function isBlocked(string $domain): bool
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

        try {
            return DB::row('
SELECT `domain`
    FROM `BlockedServers`
    WHERE `domain` IN (' . $placeholders . ')
', 'BlockedServerData', str_repeat('s', count($candidates)), ...$candidates) !== null;
        } catch (\mysqli_sql_exception $exception) {
            // 1146 is "no such table". Every outbound request passes through
            // this, including the ones bin/install.php makes while checking the
            // environment - which happens before the schema step has created
            // anything, so on a database being upgraded from a version that
            // predates this table there is nothing to read. A blocklist that
            // does not exist yet blocks nothing.
            //
            // Narrowed to that one code deliberately: any other database
            // failure here has to keep throwing rather than be read as
            // permission to make the request.
            if ($exception -> getCode() !== 1146) {
                throw $exception;
            }

            return false;
        }
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
