<?php

declare(strict_types=1);

class OutgoingFriendRequestList extends UserList
{
    protected string $listType = 'outgoing';

    protected function rows(): array
    {
        $pending = 'pending';
        $not_banned = 0;

        return DB::rows('
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`addresseeId`
    WHERE `f`.`requesterId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ? OFFSET ?
', 'SentFriendRequest', 'isiii', (int) $this -> user -> userId, $pending, $not_banned, static::PAGE_SIZE + 1, $this -> offset);
    }
}
