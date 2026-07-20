<?php

declare(strict_types=1);

class FriendList extends UserList
{
    protected string $listType = 'friends';

    protected function rows(): array
    {
        $accepted = 'accepted';
        $not_banned = 0;
        $limit = static::PAGE_SIZE + 1;
        $user_id = (int) $this -> user -> userId;

        // The two friendship directions run as separate UNION ALL halves rather
        // than one OR: each half walks its (requesterId|addresseeId, status,
        // friendshipId) index backward and stops at its limit, so only the
        // merged rows ever get sorted - an OR index_merge would collect and
        // filesort every accepted friendship first. The banned filter stays
        // inside each half so pagination lines up across the two. The outer
        // OFFSET skips rows from the merged set, so each half must produce
        // every row up to offset + limit for the page to be complete.
        $half_limit = $this -> offset + $limit;

        return DB::rows('
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
UNION ALL
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
    ORDER BY `friendshipId` DESC
    LIMIT ? OFFSET ?
', 'Friend', 'isiiisiiii', $user_id, $accepted, $not_banned, $half_limit, $user_id, $accepted, $not_banned, $half_limit, $limit, $this -> offset);
    }
}
