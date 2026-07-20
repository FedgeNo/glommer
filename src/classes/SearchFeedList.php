<?php

declare(strict_types=1);

/**
 * The posts matching a search, newest first. authorId scopes the search to one
 * profile's posts; 0 searches everyone. Empty on the server until there's a
 * query - the client fills it from api/search-posts.php as you type.
 *
 * Blocking deliberately doesn't filter this. A block exists to stop
 * interaction - replying, messaging - not to hide posts that are public
 * anyway: anyone can read them signed out, so filtering them here would only
 * inconvenience the blocker while changing nothing about who can actually see
 * what.
 *
 * Remote-origin posts ARE excluded: a followed Fediverse account's posts are
 * for whoever followed it, and search answers anyone who asks.
 */
class SearchFeedList extends FeedList
{
    protected string $feedType = 'search';

    public string $query = '';
    public int $authorId = 0;

    protected function rows(): array
    {
        if ($this -> query === '') {
            return [];
        }

        $not_banned = 0;

        return Post::withItemsAndCounts(DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE MATCH(`Posts`.`title`, `Posts`.`description`, `Posts`.`keywords`) AGAINST (? IN NATURAL LANGUAGE MODE)
        AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`remoteObjectURI` IS NULL
        AND (? = 0 OR `Posts`.`userId` = ?)
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'siiiii', $this -> query, $not_banned, $this -> authorId, $this -> authorId, static::PAGE_SIZE + 1, $this -> offset));
    }
}
