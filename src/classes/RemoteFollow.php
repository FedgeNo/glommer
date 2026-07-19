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

    /** @return array{ok: bool, handle: string, error: ?string} */
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

        self::upsertShadowUser($actor);

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `remoteActorURI` = VALUES(`remoteActorURI`)
', 'iss', $local_user_id, $actor['id'], 'pending');

        if (!self::sendFollow($actor)) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'Could not deliver the follow request to that server.'];
        }

        return ['ok' => true, 'handle' => $handle, 'error' => null];
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

        $slug = self::uniqueShadowSlug($actor['preferredUsername'] !== '' ? $actor['preferredUsername'] : 'fedi');
        $synthetic_email = 'remote+' . substr(sha1($actor['id']), 0, 32) . '@glommer.invalid';
        $unusable_hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', $slug, $synthetic_email, $unusable_hash, $display_name, $actor['id'], $actor['publicKeyPem'], 1);
    }

    private static function uniqueShadowSlug(string $preferred_username): string
    {
        $sanitise = static fn (string $raw): string => (string) preg_replace('/[^a-z0-9_]/', '', strtolower($raw));

        $base = 'fedi-' . $sanitise($preferred_username);
        $base = substr($base, 0, 24);
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

            $candidate = substr($base, 0, 20) . random_int(1000, 999999);
        }

        return substr($base, 0, 16) . bin2hex(random_bytes(6));
    }

    private static function sendFollow(array $actor): bool
    {
        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => ServerURL::absolute('/activitypub/follows/' . bin2hex(random_bytes(16))),
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

        if ($parts === false || !isset($parts['host'], $parts['path'])) {
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
