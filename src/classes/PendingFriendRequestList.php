<?php

declare(strict_types=1);

class PendingFriendRequestList extends UserListSection
{
    protected string $listType = 'incoming';
    protected string $heading = 'Pending requests';

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $pending = 'pending';
        $not_banned = 0;

        $this -> items = DB::rows('
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ? OFFSET ?
', 'FriendRequest', 'isiii', (int) $this -> user -> userId, $pending, $not_banned, static::PAGE_SIZE + 1, $this -> offset);
    }
}
