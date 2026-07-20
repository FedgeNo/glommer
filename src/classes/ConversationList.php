<?php

declare(strict_types=1);

/**
 * The /messages inbox: one Conversation per person the viewer has exchanged
 * messages with, most-recent first, fetched at render time. Build with
 * new ConversationList(['userId' => 5]).
 */
class ConversationList extends ItemList
{
    public ?string $class = 'ConversationList d-flex flex-column';

    public ?int $userId = null;

    protected string $emptyNotice = 'You don\'t have any conversations yet.';

    protected function rows(): array
    {
        $not_banned = 0;

        // Each direction is its own indexed half (senderId=me walks
        // senderId_recipientId_messageId, recipientId=me walks
        // recipientId_senderId_messageId), so the scan is bounded to this user's
        // own messages rather than the whole table - a single OR across the two
        // columns can't use either index and degrades to a full scan for a
        // heavy-sending account. Each half takes MAX(messageId) per partner so
        // it groups entirely within its covering index (createdAt isn't in
        // either index, so aggregating it would read every message row); the
        // one latest-message row per partner is then fetched by primary key
        // for its createdAt. messageId order IS send order, so newest id =
        // newest message.
        return DB::rows('
SELECT `u`.`userId`, `u`.`slug`, `u`.`title`, `u`.`hasAvatar`, `m`.`createdAt` AS `lastMessageAt`
    FROM (
        SELECT `partnerId`, MAX(`lastId`) AS `lastId`
            FROM (
                SELECT `recipientId` AS `partnerId`, MAX(`messageId`) AS `lastId`
                    FROM `Messages`
                    WHERE `senderId` = ?
                    GROUP BY `recipientId`
                UNION ALL
                SELECT `senderId` AS `partnerId`, MAX(`messageId`) AS `lastId`
                    FROM `Messages`
                    WHERE `recipientId` = ?
                    GROUP BY `senderId`
            ) AS `halves`
            GROUP BY `partnerId`
    ) AS `partners`
    JOIN `Messages` `m` ON `m`.`messageId` = `partners`.`lastId`
    JOIN `Users` `u` ON `u`.`userId` = `partners`.`partnerId`
    WHERE `u`.`banned` = ?
    ORDER BY `partners`.`lastId` DESC
    LIMIT ? OFFSET ?
', 'Conversation', 'iiiii', (int) $this -> userId, (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset);
    }
}
