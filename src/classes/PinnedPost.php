<?php

declare(strict_types=1);

/**
 * Posts a member has pinned to the top of their own profile.
 *
 * Capped, because a pinned list that can hold everything pins nothing - the
 * whole value is that a visitor can tell at a glance what someone wants read
 * first.
 *
 * Their own posts only. Pinning is a statement about your profile, not a way to
 * put someone else's writing at the top of it.
 */
class PinnedPost
{
    /** Mastodon's limit too, so a federated profile carries the same shape. */
    public const MAX_PINNED = 5;

    /**
     * Pins a post. Returns false when it is not the member's own, when it does
     * not exist, or when they are already at the cap - the caller says which,
     * so a person is told why rather than watching nothing happen.
     */
    public static function pin(int $user_id, int $post_id): bool
    {
        if (!self::ownsPost($user_id, $post_id) || self::countFor($user_id) >= self::MAX_PINNED) {
            return false;
        }

        DB::run('
INSERT IGNORE INTO `PinnedPosts` (`userId`, `postId`)
    VALUES (?, ?)
', 'ii', $user_id, $post_id);

        return true;
    }

    public static function unpin(int $user_id, int $post_id): void
    {
        DB::run('
DELETE FROM `PinnedPosts`
    WHERE `userId` = ? AND `postId` = ?
', 'ii', $user_id, $post_id);
    }

    public static function isPinned(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `PinnedPosts`
    WHERE `userId` = ? AND `postId` = ?
', 'PinnedPostData', 'ii', $user_id, $post_id) !== null;
    }

    public static function countFor(int $user_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `PinnedPosts`
    WHERE `userId` = ?
', 'PostCountData', 'i', $user_id);

        return $row === null ? 0 : (int) $row -> total;
    }

    /**
     * The pinned posts themselves, newest pin first, with their media loaded.
     *
     * @return Post[]
     */
    public static function postsFor(int $user_id): array
    {
        $limit = self::MAX_PINNED;

        $posts = DB::rows('
SELECT `Posts`.*
    FROM `PinnedPosts`
    JOIN `Posts` ON `Posts`.`postId` = `PinnedPosts`.`postId`
    WHERE `PinnedPosts`.`userId` = ?
    ORDER BY `PinnedPosts`.`createdAt` DESC
    LIMIT ?
', 'Post', 'ii', $user_id, $limit);

        return $posts === [] ? [] : Post::fromRowsWithItems($posts);
    }

    /**
     * The pinned posts' URIs, for the actor's featured collection.
     *
     * @return string[]
     */
    public static function objectURIsFor(User $user): array
    {
        if ($user -> userId === null) {
            return [];
        }

        $uris = [];

        foreach (self::postsFor((int) $user -> userId) as $post) {
            // A post that came from elsewhere keeps its own URI - pinning
            // someone else's writing to your profile does not make it yours to
            // republish.
            $uris[] = is_string($post -> remoteObjectURI) && $post -> remoteObjectURI !== ''
                ? $post -> remoteObjectURI
                : ActivityPubNote::uriFor($post, $user);
        }

        return $uris;
    }

    private static function ownsPost(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `postId` = ? AND `userId` = ?
', 'PinnedPostData', 'ii', $post_id, $user_id) !== null;
    }
}
