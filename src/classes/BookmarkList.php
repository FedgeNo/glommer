<?php

declare(strict_types=1);

/**
 * The /bookmarks page's list of the viewer's bookmarked posts, fetched straight
 * into its own contents at construction and grown by infinite scroll (main.js).
 * Ordered by bookmarkId - insertion order, i.e. most recently bookmarked first,
 * which is the ordering this list is about ("most recently bookmarked" and
 * "most recently posted" are genuinely different here). Paginated by offset:
 * the client asks for the next page by saying how many posts it already shows.
 * Build with new BookmarkList(['userId' => 5]).
 */
class BookmarkList extends ItemList
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'BookmarkList d-flex flex-column';

    public ?int $userId = null;
    public int $offset = 0;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $not_banned = 0;

        $this -> contents = Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Bookmarks`
    JOIN `Posts` ON `Posts`.`postId` = `Bookmarks`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Bookmarks`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Bookmarks`.`bookmarkId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iiii', (int) $this -> userId, $not_banned, self::PAGE_SIZE + 1, $this -> offset));
    }

    public function toDOM(): \DOMElement
    {
        $has_more = count($this -> contents) > self::PAGE_SIZE;

        if ($has_more) {
            array_pop($this -> contents);
        }

        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
