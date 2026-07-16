<?php

declare(strict_types=1);

/**
 * The /bookmarks page's list of the viewer's bookmarked posts. Mirrors
 * FeedList, but cursors on (createdAt, postId) - when each post was
 * bookmarked - rather than a bare oldestPostId, since "most recently
 * bookmarked first" and "most recently posted first" are genuinely different
 * orderings here.
 */
class BookmarkList extends Div
{
    public ?string $class = 'BookmarkList d-flex flex-column gap-4';

    public ?string $oldestBookmarkCreatedAt = null;
    public ?int $oldestBookmarkPostId = null;
    public bool $hasMore = false;

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

    /**
     * @param array[] $rows Post rows, newest-bookmarked first.
     */
    public static function fromRows(array $rows, bool $has_more, ?string $oldest_bookmark_created_at, ?int $oldest_bookmark_post_id): self
    {
        $list = new self();
        $list -> hasMore = $has_more;
        $list -> oldestBookmarkCreatedAt = $oldest_bookmark_created_at;
        $list -> oldestBookmarkPostId = $oldest_bookmark_post_id;

        $list -> addContents(Post::withItemsAndCounts($rows));

        return $list;
    }
}
