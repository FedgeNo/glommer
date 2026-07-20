<?php

declare(strict_types=1);

/**
 * The /bookmarks page's list of the viewer's bookmarked posts, grown by
 * infinite scroll (main.js). Ordered by bookmarkId - insertion order, i.e. most
 * recently bookmarked first, which is the ordering this list is about ("most
 * recently bookmarked" and "most recently posted" are genuinely different
 * here). Build with new BookmarkList(['userId' => 5]).
 */
class BookmarkList extends ItemList
{
    public ?string $class = 'BookmarkList d-flex flex-column';

    public ?int $userId = null;

    protected function rows(): array
    {
        $not_banned = 0;

        return Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Bookmarks`
    JOIN `Posts` ON `Posts`.`postId` = `Bookmarks`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Bookmarks`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Bookmarks`.`bookmarkId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset));
    }
}
