<?php

declare(strict_types=1);

/**
 * Someone out on the Fediverse following a local member - the opposite
 * direction from RemoteFollow, which is a local member following outward.
 *
 * Its own table rather than a Friendships row: Friendships permits one row per
 * pair in either direction (uniq_unordered_pair), while federated follows are
 * one-way and independent, and two people following each other is two edges
 * rather than one.
 *
 * The follower's inbox is stored alongside the edge rather than looked up when
 * needed, so publishing a post doesn't have to dereference every follower's
 * actor document first. sharedInboxURL is the batching endpoint, where the
 * remote server offers one - many followers on the same server collapse to a
 * single delivery through it.
 */
class FediverseFollower
{
    /**
     * Records a follow, or refreshes the inbox on one already held. A remote
     * server re-sending Follow is normal - it is how they recover after losing
     * state - so this is an upsert rather than an error.
     */
    public static function add(int $local_user_id, string $remote_actor_uri, string $inbox_url, ?string $shared_inbox_url, string $follow_activity_id): void
    {
        DB::run('
INSERT INTO `FediverseFollowers` (`localUserId`, `remoteActorURI`, `inboxURL`, `sharedInboxURL`, `followActivityId`)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `inboxURL` = VALUES(`inboxURL`), `sharedInboxURL` = VALUES(`sharedInboxURL`), `followActivityId` = VALUES(`followActivityId`)
', 'issss', $local_user_id, $remote_actor_uri, $inbox_url, $shared_inbox_url, $follow_activity_id);
    }

    public static function remove(int $local_user_id, string $remote_actor_uri): void
    {
        DB::run('
DELETE FROM `FediverseFollowers`
    WHERE `localUserId` = ? AND `remoteActorURI` = ?
', 'is', $local_user_id, $remote_actor_uri);
    }

    /** Drops every follow by one actor, across all members - for a defederation. */
    public static function removeActor(string $remote_actor_uri): void
    {
        DB::run('
DELETE FROM `FediverseFollowers`
    WHERE `remoteActorURI` = ?
', 's', $remote_actor_uri);
    }

    public static function exists(int $local_user_id, string $remote_actor_uri): bool
    {
        return DB::row('
SELECT `fediverseFollowerId`
    FROM `FediverseFollowers`
    WHERE `localUserId` = ? AND `remoteActorURI` = ?
', 'FediverseFollowerData', 'is', $local_user_id, $remote_actor_uri) !== null;
    }

    /**
     * One page of the actor URIs following this member, newest first - the
     * followers collection, as the network reads it.
     *
     * Paged rather than whole: a popular account's followers do not fit in one
     * response, and a collection that tries anyway gets slower the more it has
     * to say.
     *
     * @return string[]
     */
    public static function actorURIsFor(int $local_user_id, int $page = 1): array
    {
        $limit = ActivityPubCollection::PAGE_SIZE;
        $offset = (max(1, $page) - 1) * $limit;

        $rows = DB::rows('
SELECT `remoteActorURI`
    FROM `FediverseFollowers`
    WHERE `localUserId` = ?
    ORDER BY `fediverseFollowerId` DESC
    LIMIT ? OFFSET ?
', 'FediverseFollowerData', 'iii', $local_user_id, $limit, $offset);

        return array_map(static fn (FediverseFollowerData $row): string => (string) $row -> remoteActorURI, $rows);
    }

    public static function countFor(int $local_user_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `FediverseFollowers`
    WHERE `localUserId` = ?
', 'PostCountData', 'i', $local_user_id);

        return $row === null ? 0 : (int) $row -> total;
    }

    /**
     * Where to deliver this member's posts. One entry per destination inbox
     * rather than per follower: a server offering a shared inbox takes one
     * delivery for everyone it hosts, which is the difference between one
     * request and hundreds when a large instance follows someone.
     *
     * @return string[]
     */
    public static function deliveryInboxesFor(int $local_user_id): array
    {
        $rows = DB::rows('
SELECT `inboxURL`, `sharedInboxURL`
    FROM `FediverseFollowers`
    WHERE `localUserId` = ?
', 'FediverseFollowerData', 'i', $local_user_id);

        $inboxes = [];

        foreach ($rows as $row) {
            $target = ($row -> sharedInboxURL !== null && $row -> sharedInboxURL !== '')
                ? $row -> sharedInboxURL
                : (string) $row -> inboxURL;

            if ($target !== '') {
                $inboxes[$target] = true;
            }
        }

        return array_keys($inboxes);
    }
}
