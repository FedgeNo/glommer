<?php

declare(strict_types=1);

/**
 * One profile's own top-level posts, newest first.
 */
class ProfileFeedList extends FeedList
{
    protected string $feedType = 'user';

    public ?int $userId = null;

    protected function rows(): array
    {
        $not_banned = 0;

        return Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Posts`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return parent::dataAttributes() + ['data-user-id' => (string) $this -> userId];
    }
}
