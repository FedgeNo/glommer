<?php

declare(strict_types=1);

/**
 * A member here following a Fediverse account: resolves the handle, fetches
 * the remote actor, creates or reuses a shadow Users row for them, and
 * delivers a Follow signed by the member.
 *
 * The follow is the member's own at the protocol level, not the instance's.
 * The person on the other end sees who is actually following them rather than
 * a server name, which is both what they would expect and what lets them
 * decide about that person rather than about this site. Two members following
 * the same account is therefore two follows, which is simply what it is.
 */
class RemoteFollow
{
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

    /**
     * The remote actors this member follows, for the published following
     * collection. Only accepted ones: a pending follow is a request the far
     * side has not answered, and listing it would state a relationship that
     * does not exist yet.
     *
     * @return string[]
     */
    public static function acceptedActorURIsFor(int $local_user_id, int $page = 1): array
    {
        $accepted = 'accepted';
        $limit = ActivityPubCollection::PAGE_SIZE;
        $offset = (max(1, $page) - 1) * $limit;

        $rows = DB::rows('
SELECT `remoteActorURI`
    FROM `RemoteFollows`
    WHERE `localUserId` = ? AND `status` = ?
    ORDER BY `remoteFollowId` DESC
    LIMIT ? OFFSET ?
', 'RemoteFollowData', 'isii', $local_user_id, $accepted, $limit, $offset);

        return array_map(static fn (RemoteFollowData $row): string => (string) $row -> remoteActorURI, $rows);
    }

    /** How many accepted follows this member holds, for the collection's total. */
    public static function acceptedCountFor(int $local_user_id): int
    {
        $accepted = 'accepted';

        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `RemoteFollows`
    WHERE `localUserId` = ? AND `status` = ?
', 'PostCountData', 'is', $local_user_id, $accepted);

        return $row === null ? 0 : (int) $row -> total;
    }

    /** @return array{ok: bool, handle: string, error: ?string, userId?: ?int} */
    public static function create(int $local_user_id, string $user, string $domain): array
    {
        $handle = '@' . $user . '@' . $domain;
        $follower = User::load($local_user_id);

        // The member's own key signs this now, not the instance's - so what
        // matters is whether THEY can sign, which also fails when no encryption
        // secret is configured and no key could be stored safely.
        if ($follower === null || ActivityPubActor::privateKeyPem($follower) === null) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'ActivityPub is not set up on this server yet.'];
        }

        $actor_uri = WebFinger::resolveActorURI($user, $domain);

        if ($actor_uri === null) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'Could not resolve that account.'];
        }

        // Two members of this server must not federate with each other - they
        // already share a feed, and an edge over the wire on top of that would
        // deliver every post twice. Checked on the resolved URI rather than the
        // typed domain, since a personal domain can delegate back to us.
        if (ActivityPubActor::isLocalActorURI($actor_uri)) {
            return ['ok' => false, 'handle' => $handle, 'error' => 'That account is on this server. Follow them here instead - going the long way round the Fediverse would show you their posts twice.'];
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

        RemoteActor::upsert($actor);

        // Recorded before delivery, because the Accept answering it is what
        // this id exists to match and it can arrive the moment we send.
        $follow_activity_id = ServerURL::absolute('/activitypub/follows/' . bin2hex(random_bytes(16)));

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`, `followActivityId`)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `followActivityId` = VALUES(`followActivityId`)
', 'isss', $local_user_id, $actor['id'], 'pending', $follow_activity_id);

        if (!self::sendFollow($actor, $follow_activity_id, $follower)) {
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
        // Same rule as create(): never federate with our own members.
        if (ActivityPubActor::isLocalActorURI($remote_actor_uri)) {
            return false;
        }

        $actor = RemoteActor::fetch($remote_actor_uri);

        if ($actor === null || $actor['id'] !== $remote_actor_uri) {
            return false;
        }

        RemoteActor::upsert($actor);

        $follow_activity_id = ServerURL::absolute('/activitypub/follows/' . bin2hex(random_bytes(16)));

        DB::run('
INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `status`, `followActivityId`)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `followActivityId` = VALUES(`followActivityId`)
', 'isss', $local_user_id, $actor['id'], 'pending', $follow_activity_id);

        $follower = User::load($local_user_id);

        return $follower !== null && self::sendFollow($actor, $follow_activity_id, $follower);
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

        // Each member holds their own follow at the protocol level now, so
        // this one is theirs to withdraw - another member following the same
        // account has a separate edge that is none of this person's business.
        $follower = User::load($local_user_id);

        if ($follower !== null && $follow -> followActivityId !== null) {
            self::sendUndoFollow($remote_actor_uri, $follow -> followActivityId, $follower);
        }

        return true;
    }

    private static function sendUndoFollow(string $remote_actor_uri, string $follow_activity_id, User $follower): void
    {
        $actor = RemoteActor::fetch($remote_actor_uri);

        if ($actor === null) {
            return;
        }

        $follower_uri = ActivityPubActor::uriFor($follower);

        ActivityPubDelivery::postAs($follower, $actor['inbox'], [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $follower_uri . '#undos/' . bin2hex(random_bytes(8)),
            'type' => 'Undo',
            'actor' => $follower_uri,
            'object' => [
                'id' => $follow_activity_id,
                'type' => 'Follow',
                'actor' => $follower_uri,
                'object' => $remote_actor_uri,
            ],
        ]);
    }

    private static function sendFollow(array $actor, string $follow_activity_id, User $follower): bool
    {
        $activity = [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $follow_activity_id,
            'type' => 'Follow',
            'actor' => ActivityPubActor::uriFor($follower),
            'object' => $actor['id'],
        ];

        return ActivityPubDelivery::postAs($follower, $actor['inbox'], $activity);
    }
}
