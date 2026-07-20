<?php

declare(strict_types=1);

/**
 * A profile's "Posts" section: the heading over that user's own posts, and the
 * post-search box scoped to them. A page builds one from a user id and asks
 * hasItems() whether there's anything to show at all.
 */
class ProfileFeed extends ListSection
{
    public ?string $class = 'ProfileFeed';

    protected string $heading = 'Posts';

    protected string $itemsClass = 'FeedList d-flex flex-column';

    public ?int $userId = null;

    protected function pagesOnItems(): bool
    {
        return true;
    }

    /**
     * The scroll handler grows this feed by appending to the <ul>, so the <ul>
     * is where it looks for which feed this is and whose.
     *
     * @return array<string, string>
     */
    protected function itemsAttributes(): array
    {
        return [
            'data-feed-type' => 'user',
            'data-user-id' => (string) $this -> userId,
        ];
    }

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
}
