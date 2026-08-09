<?php

declare(strict_types=1);

/**
 * The posts matching a search, newest first. userId scopes the search to one
 * profile's posts; 0 searches everyone. Empty on the server until there's a
 * query - the client fills it from api/search-posts.php as you type.
 *
 * Blocking deliberately doesn't filter this. A block exists to stop
 * interaction - replying, messaging - not to hide posts that are public
 * anyway: anyone can read them signed out, so filtering them here would only
 * inconvenience the blocker while changing nothing about who can actually see
 * what.
 *
 * Remote-origin posts are included, unlike every public listing: searching is
 * for members only (both the page and api/search-posts.php refuse anyone else),
 * so nothing found here is being shown to the world - and a member searching
 * their own server should find what it actually holds.
 */
class SearchFeedList extends FeedList
{
    public string $query = '';
    public int $userId = 0;

    /**
     * No server-side scroll config: a search's next page depends on what's
     * currently typed, so Search.js builds the request (against
     * api/search-posts.php) and drives this list's paging itself. Advertising
     * one here would bind a second scroller to the same list, asking api/feed.php
     * for a feed type it doesn't serve.
     */
    protected function scrollConfig(): ?array
    {
        return null;
    }

    protected function rows(): array
    {
        if ($this -> query === '') {
            return [];
        }

        $not_banned = 0;
        $viewer_id = (int) Auth::id();

        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE MATCH(`Posts`.`title`, `Posts`.`description`, `Posts`.`keywords`) AGAINST (? IN NATURAL LANGUAGE MODE)
        AND `Users`.`banned` = ?
        AND (? = 0 OR `Posts`.`userId` = ?)
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'iisiiiii', $viewer_id, $viewer_id, $this -> query, $not_banned, $this -> userId, $this -> userId, static::PAGE_SIZE + 1, $this -> offset));
    }
}
