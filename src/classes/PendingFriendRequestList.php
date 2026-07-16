<?php

declare(strict_types=1);

class PendingFriendRequestList extends UserList
{
    protected string $listType = 'incoming';
    protected string $heading = 'Pending requests';
    protected string $emptyMessage = 'No pending requests.';

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $pending = 'pending';
        $not_banned = 0;

        $this -> items = DB::rows('
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?
', 'FriendRequest', 'isiii', (int) $this -> user -> userId, $pending, $not_banned, $this -> before ?? PHP_INT_MAX, static::PAGE_SIZE + 1);
    }
}
