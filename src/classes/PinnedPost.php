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
    public ?int $userId = null;
    public ?int $postId = null;
    public ?string $createdAt = null;

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

    /**
     * Which of these posts this member has pinned. One query for a page rather
     * than one per card - only your own posts can be pinned, so this is only
     * ever asked about the person looking.
     *
     * @param int[] $post_ids
     * @return array<int, true> keyed by postId
     */
    public static function pinnedForPosts(array $post_ids, int $user_id): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $result = mysqli_stmt_get_result(DB::run('
SELECT `postId`
    FROM `PinnedPosts`
    WHERE `userId` = ? AND `postId` IN (' . $placeholders . ')
', 'i' . str_repeat('i', count($post_ids)), $user_id, ...$post_ids));

        $pinned = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $pinned[(int) $row['postId']] = true;
        }

        return $pinned;
    }

    public static function isPinned(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `PinnedPosts`
    WHERE `userId` = ? AND `postId` = ?
', 'PinnedPostData', 'ii', $user_id, $post_id) !== null;
    }

    /**
     * Counts what the profile actually shows, joining Posts the same way
     * postsFor() does. The cap is enforced from this, and a pin whose post no
     * longer exists is invisible - so counting it would hold a slot its owner
     * has no way to reach and free. The cascade normally removes such a row
     * with the post; this is what keeps the cap honest if one ever survives.
     */
    public static function countFor(int $user_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `PinnedPosts`
    JOIN `Posts` ON `Posts`.`postId` = `PinnedPosts`.`postId`
    WHERE `PinnedPosts`.`userId` = ?
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

        $viewer_id = (int) Auth::id();

        $posts = DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `PinnedPosts`
    JOIN `Posts` ON `Posts`.`postId` = `PinnedPosts`.`postId`
    WHERE `PinnedPosts`.`userId` = ?
    ORDER BY `PinnedPosts`.`createdAt` DESC
    LIMIT ?
', 'Post', 'iiii', $viewer_id, $viewer_id, $user_id, $limit);

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

    /**
     * Tells followers elsewhere that this member's featured collection changed.
     *
     * The collection is served and could simply be re-read, but nothing would
     * prompt anyone to re-read it - a profile fetched once stays as it was
     * fetched. Add/Remove is how the rest of the network says a pin changed,
     * so it is how a pin here changes anywhere else.
     */
    public static function publish(User $user, int $post_id, bool $pinning): void
    {
        if ($user -> remoteActorURI !== null || $user -> userId === null) {
            return;
        }

        $object_uri = self::objectURIFor($post_id);

        if ($object_uri === null) {
            return;
        }

        FediverseDelivery::fanOut($user, [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            // Stable per (person, post, direction), so pinning something twice
            // is the same activity rather than one the far side must reconcile.
            'id' => ServerURL::absolute('/activitypub/featured/' . ($pinning ? 'add' : 'remove') . '/' . (int) $user -> userId . '-' . $post_id),
            'type' => $pinning ? 'Add' : 'Remove',
            'actor' => ActivityPubActor::uriFor($user),
            'object' => $object_uri,
            'target' => ActivityPubActor::featuredFor($user),
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            'cc' => [ActivityPubActor::followersFor($user)],
        ]);
    }

    /** What was pinned: its own URI when the post came from elsewhere. */
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

    private static function ownsPost(int $user_id, int $post_id): bool
    {
        return DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `postId` = ? AND `userId` = ?
', 'PinnedPostData', 'ii', $post_id, $user_id) !== null;
    }
}
