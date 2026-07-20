<?php

declare(strict_types=1);

/**
 * Creates/updates the local bookkeeping for "this local user wants this
 * remote Fediverse account's posts in their feed": resolves the handle,
 * fetches the remote actor, creates or reuses a shadow Users row for them,
 * and delivers a signed Follow. The ActivityPub-level follow relationship is
 * between this Glommer instance (the one site-wide actor - see
 * ActivityPubKeys) and the remote account; RemoteFollows is purely our own
 * bookkeeping of which local users get that account fanned into their feed,
 * so two local users following the same remote account only costs one real
 * follow at the protocol level.
 */
class RemoteFollow
{
    private const MAX_RESPONSE_BYTES = 65536;

    /** Users.slug is varchar(255) - wide enough to hold a whole handle unaltered. */
    private const MAX_SLUG_LENGTH = 255;

    // Declared so a row fetched via DB::row()/DB::rows() doesn't set them as
    // deprecated dynamic properties.
    public ?int $remoteFollowId = null;
    public ?int $localUserId = null;
    public ?string $remoteActorURI = null;
    public ?string $status = null;
    public ?string $followActivityId = null;
    public ?string $createdAt = null;

    /** @return array<int, array{displayName: string, status: string}> */
    public static function listForUser(int $local_user_id): array
    {
        $stmt = DB::run('
SELECT `Users`.`title`, `RemoteFollows`.`status`
    FROM `RemoteFollows`
    JOIN `Users` ON `Users`.`remoteActorURI` = `RemoteFollows`.`remoteActorURI`
    WHERE `RemoteFollows`.`localUserId` = ?
    ORDER BY `RemoteFollows`.`createdAt` DESC
', 'i', $local_user_id);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = ['displayName' => (string) $row['title'], 'status' => (string) $row['status']];
        }

        return $rows;
    }

    /** @return array{ok: bool, handle: string, error: ?string, userId?: ?int} */
    public static function create(int $local_user_id, string $user, string $domain): array
    {
        $handle = '@' . $user . '@' . $domain;

        if (!ActivityPubKeys::isConfigured()) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'ActivityPub is not set up on this server yet.'];
        }

        $actor_uri = WebFinger::resolveActorURI($user, $domain);

        if ($actor_uri === null) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'Could not resolve that account.'];
        }

        $actor = RemoteActor::fetch($actor_uri);

        if ($actor === null) {
            return ['ok' => false, 'handle' => $handle, 'error' => "Could not fetch that account's profile."];
        }

        // The handle's domain pointing somewhere else is legitimate delegation
        // (a personal domain fronting for the server that really hosts the
        // account), but only if that server says so too - see
        // WebFinger::confirmsActor.
        if (strcasecmp((string) parse_url($actor['id'], PHP_URL_HOST), $domain) !== 0
            && !WebFinger::confirmsActor($actor['id'], $actor['preferredUsername'])) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'That account is on a different server than its handle claims, and that server does not confirm it.'];
        }

        self::upsertShadowUser($actor);

        // Recorded before delivery, because the Accept answering it is what
        // this id exists to match and it can arrive the moment we send.
        $follow_activity_id = ServerURL::absolute('/activitypub/follows/' . bin2hex(random_bytes(16)));

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`, `followActivityId`)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `followActivityId` = VALUES(`followActivityId`)
', 'isss', $local_user_id, $actor['id'], 'pending', $follow_activity_id);

        if (!self::sendFollow($actor, $follow_activity_id)) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'Could not deliver the follow request to that server.'];
        }

        return ['ok' => true, 'handle' => $handle, 'error' => null, 'userId' => self::shadowUserIdFor($actor['id'])];
    }

    public static function shadowUserIdFor(string $remote_actor_uri): ?int
    {
        $user = DB::row('
SELECT `userId`
    FROM `Users`
    WHERE `remoteActorURI` = ?
', 'User', 's', $remote_actor_uri);

        return $user !== null ? (int) $user -> userId : null;
    }

    /**
     * Follows an account already known to this instance, by its actor URI -
     * the Follow button on a shadow profile, where the handle was resolved
     * once already and there's nothing to look up again.
     */
    public static function createForActor(int $local_user_id, string $remote_actor_uri): bool
    {
        $actor = RemoteActor::fetch($remote_actor_uri);

        if ($actor === null || $actor['id'] !== $remote_actor_uri) {
            return false;
        }

        self::upsertShadowUser($actor);

        $follow_activity_id = ServerURL::absolute('/activitypub/follows/' . bin2hex(random_bytes(16)));

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`, `followActivityId`)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `followActivityId` = VALUES(`followActivityId`)
', 'isss', $local_user_id, $actor['id'], 'pending', $follow_activity_id);

        return self::sendFollow($actor, $follow_activity_id);
    }

    /**
     * Stops fanning a remote account's posts into this user's feed and tells
     * the remote server so it stops delivering. The local half is removed
     * regardless of whether that delivery lands - an unfollow the person
     * asked for shouldn't be held up by the other server being unreachable.
     */
    public static function remove(int $local_user_id, string $remote_actor_uri): bool
    {
        $follow = DB::row('
SELECT *
    FROM `RemoteFollows`
    WHERE `localUserId` = ? AND `remoteActorURI` = ?
', self::class, 'is', $local_user_id, $remote_actor_uri);

        if ($follow === null) {
            return false;
        }

        DB::run('
DELETE
    FROM `RemoteFollows`
    WHERE `remoteFollowId` = ?
', 'i', $follow -> remoteFollowId);

        // Their posts leave this person's feed immediately; the posts
        // themselves stay for anyone else still following the account.
        DB::run('
DELETE `Timelines`
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`remoteActorURI` = ?
', 'is', $local_user_id, $remote_actor_uri);

        // Only tell the remote server once nobody here follows them any
        // more - the follow itself is instance-wide, so another local
        // follower still wants the deliveries.
        if (self::localFollowerCount($remote_actor_uri) === 0 && $follow -> followActivityId !== null) {
            self::sendUndoFollow($remote_actor_uri, $follow -> followActivityId);
        }

        return true;
    }

    private static function localFollowerCount(string $remote_actor_uri): int
    {
        $rows = DB::rows('
SELECT `remoteFollowId`
    FROM `RemoteFollows`
    WHERE `remoteActorURI` = ?
', self::class, 's', $remote_actor_uri);

        return count($rows);
    }

    private static function sendUndoFollow(string $remote_actor_uri, string $follow_activity_id): void
    {
        $actor = RemoteActor::fetch($remote_actor_uri);

        if ($actor === null) {
            return;
        }

        self::deliver($actor['inbox'], [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => ServerURL::absolute('/activitypub/undos/' . bin2hex(random_bytes(16))),
            'type' => 'Undo',
            'actor' => ServerURL::absolute('/activitypub/actor'),
            'object' => [
                'id' => $follow_activity_id,
                'type' => 'Follow',
                'actor' => ServerURL::absolute('/activitypub/actor'),
                'object' => $remote_actor_uri,
            ],
        ]);
    }

    private static function upsertShadowUser(array $actor): void
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
    SET `title` = ?, `remoteActorPublicKeyPem` = ?
    WHERE `userId` = ?
', 'ssi', $display_name, $actor['publicKeyPem'], $existing -> userId);

            return;
        }

        $slug = self::uniqueShadowSlug($actor['preferredUsername'], $actor['id']);
        $synthetic_email = 'remote+' . substr(sha1($actor['id']), 0, 32) . '@glommer.invalid';
        $unusable_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', $slug, $synthetic_email, $unusable_hash, $display_name, $actor['id'], $actor['publicKeyPem'], 1);
    }

    /**
     * A remote account's slug is its Fediverse handle as-is, minus the
     * leading @: bob@mastodon.social. That makes the profile URL the handle
     * (/users/bob@mastodon.social/), so it needs no decoding to display and
     * no folding that could make two different handles collide.
     *
     * It also can't collide with a local username or be squatted by one:
     * api/signup.php strips local names to [a-z0-9_], so an @ is impossible
     * there, which keeps the two namespaces structurally disjoint.
     *
     * The local part is reduced to the same [a-z0-9_] Mastodon itself allows,
     * and the whole thing is lowercased - remote usernames are compared
     * case-insensitively, and the slug column's collation matches that.
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

    private static function sendFollow(array $actor, string $follow_activity_id): bool
    {
        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $follow_activity_id,
            'type' => 'Follow',
            'actor' => ServerURL::absolute('/activitypub/actor'),
            'object' => $actor['id'],
        ];

        return self::deliver($actor['inbox'], $activity);
    }

    private static function deliver(string $inbox_url, array $activity): bool
    {
        $private_key_pem = ActivityPubKeys::privateKeyPem();

        if ($private_key_pem === null) {
            return false;
        }

        $body = json_encode($activity, JSON_UNESCAPED_SLASHES);
        $parts = parse_url($inbox_url);

        // The actor id inside the activity came from a remote document, so
        // encoding can genuinely fail on invalid UTF-8 - signing false here
        // would be a TypeError rather than a failed delivery.
        if ($body === false || $parts === false || !isset($parts['host'], $parts['path'])) {
            return false;
        }

        $path = $parts['path'] . (isset($parts['query']) ? '?' . $parts['query'] : '');
        $date = gmdate('D, d M Y H:i:s') . ' GMT';
        $digest = HTTPSignature::digest($body);
        $key_id = ServerURL::absolute('/activitypub/actor') . '#main-key';

        $signature = HTTPSignature::sign('POST', $path, $parts['host'], $date, $digest, $key_id, $private_key_pem);

        $headers = [
            'Host: ' . $parts['host'],
            'Date: ' . $date,
            'Digest: ' . $digest,
            'Signature: ' . $signature,
            'Content-Type: application/activity+json',
            'Accept: application/activity+json',
        ];

        return SafeHTTPFetcher::postJSON($inbox_url, $body, $headers, self::MAX_RESPONSE_BYTES) !== null;
    }
}
