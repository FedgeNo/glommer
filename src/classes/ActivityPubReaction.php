<?php

declare(strict_types=1);

/**
 * Likes and boosts crossing the boundary in both directions.
 *
 * A like needs no storage of its own: Likes is keyed on (userId, postId), and a
 * remote account already has a shadow Users row, so somebody on Mastodon
 * favouriting a post here is the same row a member here would make. That also
 * means the count on the page is the real total rather than two numbers added
 * together, and an Undo is just the row going away again.
 *
 * A boost does need its own table. Nothing here reposts yet, so there is no
 * existing row shape to reuse, and the boost has to be remembered anyway or an
 * Undo would have nothing to find.
 */
class ActivityPubReaction
{
    /**
     * Someone out there liked a post here. Only local posts: a Like aimed at an
     * object that came from elsewhere belongs to that object's own server, and
     * recording it would double-count it.
     */
    public static function liked(string $object_uri, User $actor): void
    {
        $post_id = ActivityPubNote::localPostIdFor($object_uri);

        if ($post_id === null || $actor -> userId === null || (int) $actor -> banned === 1) {
            return;
        }

        if (Block::preventsInteractionWithPost((int) $actor -> userId, $post_id)) {
            return;
        }

        Like::create((int) $actor -> userId, $post_id);

        FediverseNotice::aboutPost($post_id, $actor, 'like');
    }

    public static function unliked(string $object_uri, User $actor): void
    {
        $post_id = ActivityPubNote::localPostIdFor($object_uri);

        if ($post_id === null || $actor -> userId === null) {
            return;
        }

        Like::remove((int) $actor -> userId, $post_id);
    }

    /** Someone out there boosted a post here. */
    public static function announced(string $object_uri, User $actor, string $activity_uri): void
    {
        $post_id = ActivityPubNote::localPostIdFor($object_uri);

        if ($post_id === null || $actor -> userId === null || (int) $actor -> banned === 1) {
            return;
        }

        if (Block::preventsInteractionWithPost((int) $actor -> userId, $post_id)) {
            return;
        }

        Repost::record((int) $actor -> userId, $post_id, $activity_uri);

        FediverseNotice::aboutPost($post_id, $actor, 'repost');
    }

    public static function unannounced(string $object_uri, User $actor): void
    {
        $post_id = ActivityPubNote::localPostIdFor($object_uri);

        if ($post_id === null || $actor -> userId === null) {
            return;
        }

        Repost::remove((int) $actor -> userId, $post_id);
    }

    /** Local reposts and remote boosts share the stored total. */
    public static function announceCount(int $post_id): int
    {
        $row = DB::row('
SELECT `repostCount`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        return $row?-> repostCount ?? 0;
    }

    /**
     * Tells a remote author that a member here liked their post, or has stopped.
     *
     * Only for posts that came from elsewhere - liking a local post is entirely
     * this server's business and there is nobody to tell.
     */
    public static function publishLike(int $post_id, User $liker, bool $liked): void
    {
        $target = self::remoteObjectFor($post_id);

        if ($target === null || $liker -> remoteActorURI !== null || $liker -> userId === null) {
            return;
        }

        $liker_uri = ActivityPubActor::uriFor($liker);

        $like = [
            'id' => $liker_uri . '#likes/' . $post_id,
            'type' => 'Like',
            'actor' => $liker_uri,
            'object' => $target['objectURI'],
        ];

        // An Undo restates the Like it withdraws, so the far side can match it
        // against what it recorded. Nested, the Like carries no context of its
        // own - the activity around it supplies that.
        $activity = $liked
            ? ['@context' => 'https://www.w3.org/ns/activitystreams'] + $like
            : [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $liker_uri . '#likes/' . $post_id . '/undo',
                'type' => 'Undo',
                'actor' => $liker_uri,
                'object' => $like,
            ];

        FediverseDelivery::enqueue((int) $liker -> userId, $activity, [$target['inboxURL']]);
    }

    /**
     * Where a post that came from elsewhere lives, and where to tell its server
     * about a reaction to it.
     *
     * @return array{objectURI: string, inboxURL: string}|null
     */
    private static function remoteObjectFor(int $post_id): ?array
    {
        $row = DB::row('
SELECT `Posts`.`remoteObjectURI`, `Users`.`remoteActorInboxURL`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Posts`.`remoteObjectURI` IS NOT NULL
', 'RemoteObjectTargetData', 'i', $post_id);

        if ($row === null || !is_string($row -> remoteObjectURI) || !is_string($row -> remoteActorInboxURL) || $row -> remoteActorInboxURL === '') {
            return null;
        }

        return ['objectURI' => $row -> remoteObjectURI, 'inboxURL' => $row -> remoteActorInboxURL];
    }
}
