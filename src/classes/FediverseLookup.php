<?php

declare(strict_types=1);

/**
 * Finding somebody on another server by their handle, from the search box.
 *
 * Searching only ever looked at accounts this server already held, which for
 * the Fediverse is the wrong way round: the whole point of a handle is that it
 * names somebody this server has never met. Typing one now makes the server go
 * and ask.
 *
 * Deliberately narrow about when it does that, because it is an outbound
 * request driven by whatever somebody typed:
 *
 * - The query has to be one handle and nothing else. A search for words that
 *   happen to contain an address is a search for words.
 * - An account already here is answered from here, with no request at all.
 * - It is rate limited per member on top of the search's own limit, since the
 *   search box asks on every keystroke and a half-typed handle can still look
 *   complete.
 * - The fetch goes through RemoteActor, so it inherits the guarded fetcher and
 *   its resolve-then-pin rules, and a blocked server is refused before any of
 *   it happens.
 *
 * What it leaves behind is an ordinary shadow account - the same row a post
 * arriving from that person would have created - so everything downstream
 * (profile, follow, block, search) already knows what to do with it.
 */
class FediverseLookup
{
    /**
     * How many strangers one member may make this server go and look for, and
     * over how long. Generous enough that nobody types their way into it,
     * small enough that nobody walks a domain's user list with it.
     */
    private const LOOKUPS = 20;
    private const WINDOW_SECONDS = 300;

    /**
     * The account a handle names, fetching it if this server has not met them
     * - or null when the query is not a handle, when nothing answers to it, or
     * when this member has asked too often.
     *
     * Never throws and never explains: this runs alongside an ordinary search
     * whose results stand on their own, and a lookup that found nothing simply
     * adds nothing to them.
     */
    public static function find(string $query): ?User
    {
        $handles = FediverseHandle::parseAll($query);

        if (count($handles) !== 1) {
            return null;
        }

        ['user' => $user, 'domain' => $domain] = $handles[0];

        // Everything else in the query has to be the handle's own punctuation
        // - a leading @, whitespace - or this is a search for a phrase that
        // happens to contain one.
        //
        // Compared without case, because the parse lowercases a handle and
        // people do not: @Gargron@mastodon.social is how that one is written
        // everywhere it appears.
        if (strcasecmp(trim($query, " \t@"), $user . '@' . $domain) !== 0) {
            return null;
        }

        $known = User::loadByUsername($user . '@' . $domain);

        if ($known !== null) {
            return $known;
        }

        if (!self::mayLookUp()) {
            return null;
        }

        return self::fetchAccount($user, $domain);
    }

    /** One member's allowance for making this server talk to strangers. */
    private static function mayLookUp(): bool
    {
        $key = 'fediverse-lookup:' . Auth::id();

        if (RateLimiter::tooManyAttempts($key, self::LOOKUPS, self::WINDOW_SECONDS)) {
            return false;
        }

        RateLimiter::recordAttempt($key);

        return true;
    }

    /**
     * Resolves the handle and stores whoever answers to it, by the same rules
     * following one by hand goes through - see RemoteFollow::byHandle, which
     * this deliberately mirrors rather than loosens.
     */
    private static function fetchAccount(string $user, string $domain): ?User
    {
        $actor_uri = WebFinger::resolveActorURI($user, $domain);

        if ($actor_uri === null || RemoteServer::isBlockedURL($actor_uri)) {
            return null;
        }

        // One of ours, reached the long way round. The ordinary search has
        // them already.
        if (ActivityPubActor::isLocalActorURI($actor_uri)) {
            return null;
        }

        $actor = RemoteActor::fetch($actor_uri);

        if ($actor === null) {
            return null;
        }

        // A handle whose domain points elsewhere is legitimate delegation - a
        // personal domain fronting the server that really hosts the account -
        // but only when that server says so too. Otherwise anyone could claim
        // anyone by pointing their own WebFinger at them.
        if (
            strcasecmp((string) parse_url($actor['id'], PHP_URL_HOST), $domain) !== 0
            && !WebFinger::confirmsActor($actor['id'], $actor['preferredUsername'])
        ) {
            return null;
        }

        RemoteActor::upsert($actor);

        return User::byRemoteActorURI($actor['id']);
    }
}
