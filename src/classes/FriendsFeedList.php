<?php

declare(strict_types=1);

/**
 * A viewer's own timeline: the posts fanned out to them, including those from
 * the Fediverse accounts they follow.
 */
class FriendsFeedList extends FeedList
{
    protected string $feedType = 'friends';

    public ?int $userId = null;

    protected function rows(): array
    {
        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        // A reposted row sorts by when it was reposted, not by the post's own
        // age - ordered by the post alone, a repost of anything older than a
        // page would land pages deep and never be seen, which made reposting
        // look like it did nothing.
        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    `reposter`.`slug` AS `repostedBySlug`,
    `reposter`.`title` AS `repostedByTitle`,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    LEFT JOIN `Users` `reposter` ON `reposter`.`userId` = `Timelines`.`reposterId`
    LEFT JOIN `Announces` ON `Announces`.`postId` = `Timelines`.`postId` AND `Announces`.`userId` = `Timelines`.`reposterId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY COALESCE(`Announces`.`createdAt`, `Posts`.`createdAt`) DESC, `Timelines`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiiiii', $viewer_id, $viewer_id, (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
