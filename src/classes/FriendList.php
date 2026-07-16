<?php

declare(strict_types=1);

class FriendList extends UserList
{
    protected string $listType = 'friends';
    protected string $heading = 'Friends';
    protected string $emptyMessage = 'You haven\'t got any friends yet.';

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $accepted = 'accepted';
        $not_banned = 0;
        $cursor = $this -> before ?? PHP_INT_MAX;
        $limit = static::PAGE_SIZE + 1;
        $user_id = (int) $this -> user -> userId;

        // The two friendship directions run as separate UNION ALL halves rather
        // than one OR: each half walks its (requesterId|addresseeId, status,
        // friendshipId) index backward and stops at the limit, so only the
        // merged 2x-limit rows ever get sorted - an OR index_merge would collect
        // and filesort every accepted friendship first. The banned filter stays
        // inside each half so pagination lines up across the two.
        $this -> items = DB::rows('
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
UNION ALL
(SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?)
    ORDER BY `friendshipId` DESC
    LIMIT ?
', 'Friend', 'isiiiisiiii', $user_id, $accepted, $not_banned, $cursor, $limit, $user_id, $accepted, $not_banned, $cursor, $limit, $limit);

        // The default message is first-person, for your own friends page; a
        // third party's empty friends list names them instead.
        if (Auth::id() !== $user_id) {
            $this -> emptyMessage = ($this -> user -> displayName ?? $this -> user -> username) . ' hasn\'t got any friends yet.';
        }
    }
}
