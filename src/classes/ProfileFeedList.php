<?php

declare(strict_types=1);

/**
 * One profile's page of writing: their own top-level posts and the posts they
 * reposted, newest act first - a repost sorts by when it was passed on, since
 * that is the act this profile performed.
 */
class ProfileFeedList extends FeedList
{
    protected string $feedType = 'user';

    public ?int $userId = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        // Two index-served halves - the member's own posts newest-first, and
        // their reposts by when they reposted - merged by sorting only the
        // handful each half was already limited to. As one query with an OR
        // across the join, no index can drive it and the whole corpus gets
        // sorted per page; measured, that is two orders of magnitude slower.
        $half_limit = static::PAGE_SIZE + 1 + $this -> offset;

        return Post::fromRowsWithItems(DB::rows('
SELECT * FROM (
    (SELECT `Posts`.*,
        NULL AS `repostedBySlug`,
        NULL AS `repostedByTitle`,
        `Posts`.`createdAt` AS `sortAt`,
        (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
        (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
        EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
        EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
        FROM `Posts`
        JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
        WHERE `Posts`.`userId` = ? AND `Users`.`banned` = ?
        ORDER BY `Posts`.`postId` DESC
        LIMIT ?)
    UNION ALL
    (SELECT `Posts`.*,
        `reposter`.`slug` AS `repostedBySlug`,
        `reposter`.`title` AS `repostedByTitle`,
        `Announces`.`createdAt` AS `sortAt`,
        (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
        (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
        EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
        EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
        FROM `Announces`
        JOIN `Posts` ON `Posts`.`postId` = `Announces`.`postId`
        JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
        JOIN `Users` `reposter` ON `reposter`.`userId` = `Announces`.`userId`
        WHERE `Announces`.`userId` = ? AND `Users`.`banned` = ?
        ORDER BY `Announces`.`createdAt` DESC
        LIMIT ?)
) `feed`
    ORDER BY `feed`.`sortAt` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiiiiiiiiii', $viewer_id, $viewer_id, (int) $this -> userId, $not_banned, $half_limit, $viewer_id, $viewer_id, (int) $this -> userId, $not_banned, $half_limit, static::PAGE_SIZE + 1, $this -> offset));
    }

    /**
     * @return array<string, mixed>
     */
    protected function scrollConfig(): array
    {
        return parent::scrollConfig() + ['userId' => (int) $this -> userId];
    }
}
