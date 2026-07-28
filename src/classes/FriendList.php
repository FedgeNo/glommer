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

    protected function dataAttributes(): array
    {
        $this->attributes['data-infinite-scroll'] = json_encode([
            'endpoint' => '/api/friend-list-history',
            'itemType' => 'OtherUser',
            'listType' => $this->listType,
            'userId'   => (int) $this->user->userId,
        ]);

        return [];
    }
}

