<?php

declare(strict_types=1);

/**
 * Passing somebody else's post on.
 *
 * Stored in Announces, the same table an inbound boost lands in - a repost here
 * and a boost from Mastodon are the same act, and keeping them in one place
 * means the count on a post is one number rather than two added together.
 *
 * Reposting does two things: it puts the post into the reposter's friends'
 * feeds, and it tells the reposter's Fediverse followers. Undoing it takes both
 * back.
 */
class Repost
{
    /**
     * Reposts a post. False when there is nothing to repost, or when it is the
     * reposter's own - passing on your own writing is what your profile is for.
     */
    public static function create(int $user_id, int $post_id): bool
    {
        $post = DB::row('
SELECT `postId`, `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'PinnedPostData', 'i', $post_id);

        if ($post === null || (int) $post -> userId === $user_id) {
            return false;
        }

        DB::run('
INSERT INTO `Announces` (`postId`, `userId`, `activityURI`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `activityURI` = VALUES(`activityURI`)
', 'iis', $post_id, $user_id, self::activityURIFor($user_id, $post_id));

        Timeline::fanOutRepost($user_id, $post_id);

        return true;
    }

    public static function remove(int $user_id, int $post_id): void
    {
        DB::run('
DELETE FROM `Announces`
    WHERE `postId` = ? AND `userId` = ?
', 'ii', $post_id, $user_id);

        Timeline::removeRepost($user_id, $post_id);
    }

    public static function exists(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `Announces`
    WHERE `postId` = ? AND `userId` = ?
', 'PinnedPostData', 'ii', $post_id, $user_id) !== null;
    }

    /**
     * Tells the reposter's followers, and the original author's server when the
     * post came from there - they want to know their post was passed on.
     */
    public static function publish(User $reposter, int $post_id, bool $reposting): void
    {
        if ($reposter -> remoteActorURI !== null || $reposter -> userId === null) {
            return;
        }

        $object_uri = self::objectURIFor($post_id);

        if ($object_uri === null) {
            return;
        }

        $reposter_uri = ActivityPubActor::uriFor($reposter);

        $announce = [
            'id' => self::activityURIFor((int) $reposter -> userId, $post_id),
            'type' => 'Announce',
            'actor' => $reposter_uri,
            'object' => $object_uri,
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'cc' => [ActivityPubActor::followersFor($reposter)],
        ];

        $activity = $reposting
            ? ['@context' => 'https://www.w3.org/ns/activitystreams'] + $announce
            : [
                '@context' => 'https://www.w3.org/ns/activitystreams',
                'id' => $announce['id'] . '/undo',
                'type' => 'Undo',
                'actor' => $reposter_uri,
                'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
                'object' => $announce,
            ];

        // The author's own server as well as the followers: a boost is news to
        // whoever wrote the thing, and they are not necessarily following back.
        FediverseDelivery::fanOut($reposter, $activity, self::authorInbox($post_id));
    }

    /**
     * Stable per (person, post), so a repost undone and redone is the same
     * activity rather than a new one the far side has to reconcile.
     */
    private static function activityURIFor(int $user_id, int $post_id): string
    {
        return ServerURL::absolute('/activitypub/announces/' . $user_id . '-' . $post_id);
    }

    /** What is being passed on: its own URI when it came from elsewhere. */
    private static function objectURIFor(int $post_id): ?string
    {
        $row = DB::row('
SELECT `Posts`.`postId`, `Posts`.`remoteObjectURI`, `Users`.`slug`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ?
', 'PostParentData', 'i', $post_id);

        if ($row === null) {
            return null;
        }

        if (is_string($row -> remoteObjectURI) && $row -> remoteObjectURI !== '') {
            return $row -> remoteObjectURI;
        }

        return ServerURL::absolute('/users/' . $row -> slug . '/' . (int) $row -> postId);
    }

    /**
     * The original author's inbox, when they are on another server.
     *
     * @return string[]
     */
    private static function authorInbox(int $post_id): array
    {
        $row = DB::row('
SELECT `Users`.`remoteActorInboxURL`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`remoteActorURI` IS NOT NULL
', 'RemoteRecipientData', 'i', $post_id);

        return $row !== null && is_string($row -> remoteActorInboxURL) && $row -> remoteActorInboxURL !== ''
            ? [$row -> remoteActorInboxURL]
            : [];
    }
}
