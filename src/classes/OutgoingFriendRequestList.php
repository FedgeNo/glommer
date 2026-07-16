<?php

declare(strict_types=1);

class OutgoingFriendRequestList extends UserList
{
    protected string $listType = 'outgoing';
    protected string $heading = 'Sent requests (awaiting response)';
    protected string $emptyMessage = 'No outgoing requests.';

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $pending = 'pending';
        $not_banned = 0;

        $this -> items = DB::rows('
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ? AND `f`.`friendshipId` < ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ?
', 'SentFriendRequest', 'isiii', (int) $this -> user -> userId, $pending, $not_banned, $this -> before ?? PHP_INT_MAX, static::PAGE_SIZE + 1);
    }
}
