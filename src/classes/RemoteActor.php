<?php

declare(strict_types=1);

/**
 * Fetches and parses a remote account's ActivityPub actor document - the
 * profile a WebFinger lookup points at, carrying its inbox URL and the
 * public key we verify its future deliveries against.
 */
class RemoteActor
{
    private const MAX_RESPONSE_BYTES = 262144;

    /** Users.remoteActorURI is varchar(255) - a longer id can't be stored, so it's refused rather than silently cut. */
    private const MAX_ACTOR_URI_LENGTH = 255;

    /** Users.title is varchar(100), counted in characters on utf8mb4. */
    private const MAX_DISPLAY_NAME_LENGTH = 100;

    /** Users.slug is varchar(255), and a shadow slug is the whole handle. */
    private const MAX_SLUG_LENGTH = 255;

    /** Far above any real RSA/Ed25519 PEM, and well inside the TEXT column that holds it. */
    private const MAX_PUBLIC_KEY_LENGTH = 8192;

    /** @return array{id: string, inbox: string, sharedInbox: ?string, publicKeyPem: string, preferredUsername: string, name: string, iconURL: ?string}|null */
    public static function fetch(string $actor_uri): ?array
    {
        $response = SafeHTTPFetcher::getJSON($actor_uri, ['Accept: application/activity+json'], self::MAX_RESPONSE_BYTES);

        if ($response === null) {
            return null;
        }

        $data = json_decode($response['body'], true);

        if (
            !is_array($data)
            || !isset($data['inbox'], $data['publicKey']['publicKeyPem'])
            || !is_string($data['inbox'])
            || !is_string($data['publicKey']['publicKeyPem'])
        ) {
            return null;
        }

        $id = is_string($data['id'] ?? null) && $data['id'] !== '' ? $data['id'] : $actor_uri;

        // The document's self-declared id decides which account this key gets
        // stored against, so a server is only allowed to speak for its own
        // host. Without this, any server could hand back an id belonging to an
        // account elsewhere together with its own key, and take over that
        // account's identity here - including overwriting the cached key of an
        // account already followed and trusted.
        if (!self::sameHost($id, $actor_uri)) {
            return null;
        }

        // The inbox is where our signed Follow gets delivered; keeping it on
        // the actor's own host stops a document from pointing our signed
        // requests at an unrelated server.
        if (!self::sameHost($data['inbox'], $id)) {
            return null;
        }

        if (strlen($id) > self::MAX_ACTOR_URI_LENGTH || strlen($data['publicKey']['publicKeyPem']) > self::MAX_PUBLIC_KEY_LENGTH) {
            return null;
        }

        $preferred_username = is_string($data['preferredUsername'] ?? null) ? $data['preferredUsername'] : '';
        $name = is_string($data['name'] ?? null) && $data['name'] !== '' ? $data['name'] : $preferred_username;
        $icon_url = is_string($data['icon']['url'] ?? null) ? $data['icon']['url'] : null;

        // The batching endpoint, where the server offers one - many followers
        // on the same instance collapse to a single delivery through it. Held
        // to the actor's own host for the same reason the inbox is: a document
        // must not be able to aim our signed requests at an unrelated server.
        $shared_inbox = $data['endpoints']['sharedInbox'] ?? null;
        $shared_inbox = is_string($shared_inbox) && self::sameHost($shared_inbox, $id) && strlen($shared_inbox) <= self::MAX_ACTOR_URI_LENGTH
            ? $shared_inbox
            : null;

        return [
            'id' => $id,
            'inbox' => $data['inbox'],
            'sharedInbox' => $shared_inbox,
            'publicKeyPem' => $data['publicKey']['publicKeyPem'],
            'preferredUsername' => mb_substr($preferred_username, 0, self::MAX_DISPLAY_NAME_LENGTH),
            'name' => mb_substr($name, 0, self::MAX_DISPLAY_NAME_LENGTH),
            'iconURL' => $icon_url,
        ];
    }

    /**
     * The local shadow row for a remote account, fetching and storing it if
     * this server has not met them before.
     *
     * Inbound federation needs this: someone following a member here is by
     * definition an actor we have never seen, and their key has to come from
     * somewhere before their signature can be checked. It comes from their own
     * server, over the guarded fetcher, and only for an id that server is
     * entitled to speak for - which is what fetch() already enforces.
     */
    public static function ensureKnown(string $actor_uri): ?User
    {
        $existing = DB::row('
SELECT *
    FROM `Users`
    WHERE `remoteActorURI` = ?
', 'User', 's', $actor_uri);

        if ($existing !== null && $existing -> remoteActorPublicKeyPem !== null) {
            return $existing;
        }

        // Never our own members: a local actor is served from here, and going
        // out to the network to ask about one would be this server interviewing
        // itself.
        if (ActivityPubActor::isLocalActorURI($actor_uri)) {
            return null;
        }

        $actor = self::fetch($actor_uri);

        if ($actor === null || $actor['id'] !== $actor_uri) {
            return null;
        }

        self::upsert($actor);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `remoteActorURI` = ?
', 'User', 's', $actor_uri);
    }

    /**
     * Writes a fetched actor into the shadow row that represents them here,
     * creating it the first time. The inbox URLs are stored so publishing does
     * not have to dereference the actor again for every post.
     *
     * @param array{id: string, inbox: string, sharedInbox: ?string, publicKeyPem: string, preferredUsername: string, name: string, iconURL: ?string} $actor
     */
    public static function upsert(array $actor): void
    {
        $existing = DB::row('
SELECT `userId`
    FROM `Users`
    WHERE `remoteActorURI` = ?
', 'User', 's', $actor['id']);

        $display_name = $actor['name'] !== '' ? $actor['name'] : $actor['preferredUsername'];

        if ($existing !== null) {
            DB::run('
UPDATE `Users`
    SET `title` = ?, `remoteActorPublicKeyPem` = ?, `remoteActorInboxURL` = ?, `remoteActorSharedInboxURL` = ?
    WHERE `userId` = ?
', 'ssssi', $display_name, $actor['publicKeyPem'], $actor['inbox'], $actor['sharedInbox'], $existing -> userId);

            return;
        }

        $slug = self::uniqueShadowSlug($actor['preferredUsername'], $actor['id']);
        $synthetic_email = 'remote+' . substr(sha1($actor['id']), 0, 32) . '@glommer.invalid';
        $unusable_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `remoteActorSharedInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
', 'ssssssssi', $slug, $synthetic_email, $unusable_hash, $display_name, $actor['id'], $actor['publicKeyPem'], $actor['inbox'], $actor['sharedInbox'], 1);
    }

    /**
     * A remote account's slug is its Fediverse handle as-is, minus the leading
     * @: bob@mastodon.social. That makes the profile URL the handle, so it
     * needs no decoding to display and no folding that could make two different
     * handles collide.
     *
     * It also cannot collide with a local username or be squatted by one:
     * api/signup.php strips local names to [a-z0-9_], so an @ is impossible
     * there, which keeps the two namespaces structurally disjoint.
     */
    private static function uniqueShadowSlug(string $preferred_username, string $actor_uri): string
    {
        $local_part = (string) preg_replace('/[^a-z0-9_]/', '', strtolower($preferred_username));
        $host = strtolower((string) parse_url($actor_uri, PHP_URL_HOST));

        if ($local_part === '' || $host === '') {
            $local_part = $local_part !== '' ? $local_part : 'user';
            $host = $host !== '' ? $host : 'unknown';
        }

        $base = substr($local_part . '@' . $host, 0, self::MAX_SLUG_LENGTH);
        $candidate = $base;

        for ($attempt = 0; $attempt < 50; $attempt++) {
            $existing = DB::row('
SELECT `slug`
    FROM `Users`
    WHERE `slug` = ?
', 'User', 's', $candidate);

            if ($existing === null) {
                return $candidate;
            }

            // Only reachable when the handle itself doesn't fit the column and
            // two different handles truncate to the same thing; a full handle
            // is unique on its own.
            $candidate = substr($base, 0, self::MAX_SLUG_LENGTH - 6) . random_int(1000, 999999);
        }

        return substr($base, 0, self::MAX_SLUG_LENGTH - 12) . bin2hex(random_bytes(6));
    }

    /**
     * Whether two URLs live on the same host. Both must be real http(s) URLs
     * with a host - anything unparseable is "not the same", so a malformed
     * value can never pass by both sides failing to parse.
     */
    private static function sameHost(string $url, string $other_url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);
        $other_host = parse_url($other_url, PHP_URL_HOST);

        if (!is_string($host) || !is_string($other_host) || $host === '' || $other_host === '') {
            return false;
        }

        if (!in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)) {
            return false;
        }

        return strcasecmp($host, $other_host) === 0;
    }
}
