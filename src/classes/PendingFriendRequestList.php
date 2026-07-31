<?php

declare(strict_types=1);

class PendingFriendRequestList extends UserList
{
    protected string $listType = 'incoming';

    protected function rows(): array
    {
        $pending = 'pending';
        $not_banned = 0;

        return DB::rows('
SELECT `f`.`friendshipId`, `u`.*
    FROM `Friendships` `f`
    JOIN `Users` `u` ON `u`.`userId` = `f`.`requesterId`
    WHERE `f`.`addresseeId` = ? AND `f`.`status` = ? AND `u`.`banned` = ?
    ORDER BY `f`.`friendshipId` DESC
    LIMIT ? OFFSET ?
', 'FriendRequest', 'isiii', (int) $this -> user -> userId, $pending, $not_banned, static::PAGE_SIZE + 1, $this -> offset);
    }

    /**
     * @return array<string, mixed>
     */
    protected function scrollConfig(): array
    {
        return [
            'endpoint' => '/api/friend-list-history',
            'itemType' => 'FriendRequest',
            'listType' => $this -> listType,
            'userId' => (int) $this -> user -> userId,
        ];
    }
}

