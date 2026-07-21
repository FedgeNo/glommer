<?php

declare(strict_types=1);

/**
 * The accepted friends of one profile matching a search, newest friendship
 * first. Empty on the server until there's a query - the client fills it from
 * api/search-friends.php as you type.
 */
class FriendSearchList extends FriendList
{
    // The generic user-section scroll handler pages by list type through
    // api/friend-list-history.php, which knows nothing about a query. This list
    // grows through its own handler and endpoint instead.
    protected string $listType = '';

    public string $query = '';

    protected function rows(): array
    {
        if ($this -> query === '') {
            return [];
        }

        $accepted = 'accepted';
        $not_banned = 0;
        $limit = static::PAGE_SIZE + 1;
        $user_id = (int) $this -> user -> userId;

        // Escape LIKE wildcards so a literal % or _ in the query doesn't match
        // everything.
        $like = '%' . addcslashes($this -> query, '\\%_') . '%';

        // The two friendship directions run as separate UNION ALL halves rather
        // than one OR: each half walks its (requesterId|addresseeId, status,
        // friendshipId) index backward and stops at its limit, so only the
        // merged rows ever get sorted. The name filter stays inside each half
        // alongside the banned filter so pagination lines up across the two.
        // The outer OFFSET skips rows from the merged set, so each half must
        // produce every row up to offset + limit for the page to be complete.
        $half_limit = $this -> offset + $limit;

        return DB::rows('
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
        AND (`u`.`slug` LIKE ? OR `u`.`title` LIKE ?)
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
UNION ALL
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
        AND (`u`.`slug` LIKE ? OR `u`.`title` LIKE ?)
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
    ORDER BY `friendshipId` DESC
    LIMIT ? OFFSET ?
', 'Friend', 'isissiisissiii', $user_id, $accepted, $not_banned, $like, $like, $half_limit, $user_id, $accepted, $not_banned, $like, $like, $half_limit, $limit, $this -> offset);
    }
}
