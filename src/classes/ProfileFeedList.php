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

        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    `reposter`.`slug` AS `repostedBySlug`,
    `reposter`.`title` AS `repostedByTitle`,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    LEFT JOIN `Announces` ON `Announces`.`postId` = `Posts`.`postId` AND `Announces`.`userId` = ?
    LEFT JOIN `Users` `reposter` ON `reposter`.`userId` = `Announces`.`userId`
    WHERE ((`Posts`.`parentId` IS NULL AND `Posts`.`userId` = ?) OR `Announces`.`userId` IS NOT NULL)
        AND `Users`.`banned` = ?
    ORDER BY COALESCE(`Announces`.`createdAt`, `Posts`.`createdAt`) DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiiiii', $viewer_id, $viewer_id, (int) $this -> userId, (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    /**
     * @return array<string, mixed>
     */
    protected function scrollConfig(): array
    {
        return parent::scrollConfig() + ['userId' => (int) $this -> userId];
    }
}
