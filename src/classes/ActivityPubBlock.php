<?php

declare(strict_types=1);

/**
 * Blocks crossing servers.
 *
 * A block that stops at this server is half a block: the account carries on
 * seeing the person who blocked them, because their own server was never told
 * and keeps delivering. Sending it makes the far side stop.
 *
 * A block also has to sever the follows between the two people, in both
 * directions and on both sides. Leaving them standing would keep delivering
 * exactly the posts the block was meant to stop.
 */
class ActivityPubBlock
{
    /**
     * Tells a remote account that a member here has blocked them, or has lifted
     * it. Signed by the member, since it is their block - the far side files it
     * against that person, not against this site.
     */
    public static function published(User $blocker, User $blocked, bool $blocking): void
    {
        if (!ActivityPubActor::isLocal($blocker)
            || $blocker -> userId === null
            || $blocked -> remoteActorURI === null
            || !is_string($blocked -> remoteActorInboxURL)
            || $blocked -> remoteActorInboxURL === '') {
            return;
        }

        $blocker_uri = ActivityPubActor::uriFor($blocker);

        $block = [
            'id' => $blocker_uri . '#blocks/' . (int) $blocked -> userId,
            'type' => 'Block',
            'actor' => $blocker_uri,
            'object' => $blocked -> remoteActorURI,
        ];

        // An Undo restates the Block it withdraws, so the far side can match it
        // against what it recorded.
        $activity = $blocking
            ? ['@context' => 'https://www.w3.org/ns/activitystreams'] + $block
            : [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $block['id'] . '/undo',
                'type' => 'Undo',
                'actor' => $blocker_uri,
                'object' => $block,
            ];

        FediverseDelivery::enqueue((int) $blocker -> userId, $activity, [$blocked -> remoteActorInboxURL]);
    }

    /**
     * A remote account blocking a member here. Recorded so this side stops
     * showing them to each other too - a block only one server honours is a
     * block that half works.
     */
    public static function received(string $target_uri, User $blocker): void
    {
        $target = ActivityPubActor::localUserFromURI($target_uri);

        if ($target === null || $target -> userId === null || $blocker -> userId === null) {
            return;
        }

        Block::create((int) $blocker -> userId, (int) $target -> userId);
        self::severFollows((int) $blocker -> userId, (int) $target -> userId, (string) $blocker -> remoteActorURI);
    }

    /** A remote account lifting a block on a member here. */
    public static function withdrawn(string $target_uri, User $blocker): void
    {
        $target = ActivityPubActor::localUserFromURI($target_uri);

        if ($target === null || $target -> userId === null || $blocker -> userId === null) {
            return;
        }

        Block::remove((int) $blocker -> userId, (int) $target -> userId);
    }

    /**
     * Drops the federated follows between two people, whichever way round they
     * ran. Local friendships are already handled by Block::create; this is the
     * half that lives out on the network.
     */
    public static function severFollows(int $remote_user_id, int $local_user_id, string $remote_actor_uri): void
    {
        // Blocking one local member from another has no federated half - both
        // are here, and there is no follow out on the network to cut.
        if ($remote_actor_uri === '') {
            return;
        }

        FediverseFollower::remove($local_user_id, $remote_actor_uri);

        DB::run('
DELETE FROM `RemoteFollows`
    WHERE `localUserId` = ? AND `remoteActorURI` = ?
', 'is', $local_user_id, $remote_actor_uri);
    }
}
