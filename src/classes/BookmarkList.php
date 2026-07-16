<?php

declare(strict_types=1);

/**
 * The /bookmarks page's list of the viewer's bookmarked posts, fetched into
 * its contents at construction. Mirrors FeedList, but cursors on (createdAt,
 * postId) - when each post was bookmarked - rather than a bare oldestPostId,
 * since "most recently bookmarked first" and "most recently posted first" are
 * genuinely different orderings here. Build with new BookmarkList(['userId' => 5]).
 */
class BookmarkList extends Div
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'BookmarkList d-flex flex-column gap-4';

    public ?int $userId = null;
    public bool $hasMore = false;
    public ?string $oldestBookmarkCreatedAt = null;
    public ?int $oldestBookmarkPostId = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        [
            'rows' => $rows,
            'hasMore' => $this -> hasMore,
            'oldestCreatedAt' => $this -> oldestBookmarkCreatedAt,
            'oldestPostId' => $this -> oldestBookmarkPostId,
        ] = Bookmark::rowsForUser((int) $this -> userId, self::PAGE_SIZE);

        $this -> contents = Post::withItemsAndCounts($rows);
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> oldestBookmarkCreatedAt !== null) {
            $this -> attributes['data-oldest-bookmark-created-at'] = $this -> oldestBookmarkCreatedAt;
        }

        if ($this -> oldestBookmarkPostId !== null) {
            $this -> attributes['data-oldest-bookmark-post-id'] = (string) $this -> oldestBookmarkPostId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }
}
