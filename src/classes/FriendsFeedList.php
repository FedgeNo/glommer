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

        return Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
