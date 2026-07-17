<?php

declare(strict_types=1);

/**
 * The /bookmarks page's list of the viewer's bookmarked posts, fetched into
 * its contents at construction. Cursors on (createdAt, postId) - when each post
 * was bookmarked - rather than a bare postId, since "most recently bookmarked
 * first" and "most recently posted first" are genuinely different orderings
 * here; the cursor defaults to a far-future sentinel so page one and a
 * load-more page are one query. Build with new BookmarkList(['userId' => 5]).
 */
class BookmarkList extends ItemList
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'BookmarkList d-flex flex-column';

    public ?int $userId = null;
    public ?string $beforeCreatedAt = null;
    public ?int $beforePostId = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $not_banned = 0;
        $before_created_at = $this -> beforeCreatedAt ?? '9999-12-31 23:59:59';
        $before_post_id = $this -> beforePostId ?? PHP_INT_MAX;

        $this -> contents = Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*, `Bookmarks`.`createdAt` AS `bookmarkedAt`
    FROM `Bookmarks`
    JOIN `Posts` ON `Posts`.`postId` = `Bookmarks`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Bookmarks`.`userId` = ? AND `Users`.`banned` = ? AND (`Bookmarks`.`createdAt` < ? OR (`Bookmarks`.`createdAt` = ? AND `Bookmarks`.`postId` < ?))
    ORDER BY `Bookmarks`.`createdAt` DESC, `Bookmarks`.`postId` DESC
    LIMIT ?
', 'Post', 'iissii', (int) $this -> userId, $not_banned, $before_created_at, $before_created_at, $before_post_id, self::PAGE_SIZE + 1));
    }

    public function toDOM(): \DOMElement
    {
        $has_more = count($this -> contents) > self::PAGE_SIZE;

        if ($has_more) {
            array_pop($this -> contents);
        }

        if ($this -> contents !== []) {
            $oldest = $this -> contents[count($this -> contents) - 1];
            $this -> attributes['data-oldest-bookmark-created-at'] = $oldest -> bookmarkedAt;
            $this -> attributes['data-oldest-bookmark-post-id'] = (string) $oldest -> postId;
        }

        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
