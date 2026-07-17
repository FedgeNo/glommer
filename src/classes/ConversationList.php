<?php

declare(strict_types=1);

/**
 * The /messages inbox: one Conversation per person the viewer has exchanged
 * messages with, most-recent first, fetched into its contents at construction.
 * Build with new ConversationList(['userId' => 5]).
 */
class ConversationList extends ItemList
{
    public ?string $class = 'ConversationList d-flex flex-column';

    public ?int $userId = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $not_banned = 0;

        // Each direction is its own indexed half (senderId=me walks
        // senderId_recipientId_messageId, recipientId=me walks
        // recipientId_senderId_messageId), so the scan is bounded to this user's
        // own messages rather than the whole table - a single OR across the two
        // columns can't use either index and degrades to a full scan for a
        // heavy-sending account. The halves collapse to one row per partner
        // (their latest message) before the join to Users for display.
        $this -> contents = DB::rows('
SELECT `u`.`userId`, `u`.`slug`, `u`.`title`, `u`.`hasAvatar`, `partners`.`lastMessageAt`
    FROM (
        SELECT `partnerId`, MAX(`createdAt`) AS `lastMessageAt`
            FROM (
                SELECT `recipientId` AS `partnerId`, `createdAt`
                    FROM `Messages`
                    WHERE `senderId` = ?
                UNION ALL
                SELECT `senderId` AS `partnerId`, `createdAt`
                    FROM `Messages`
                    WHERE `recipientId` = ?
            ) AS `mine`
            GROUP BY `partnerId`
    ) AS `partners`
    JOIN `Users` `u` ON `u`.`userId` = `partners`.`partnerId`
    WHERE `u`.`banned` = ?
    ORDER BY `partners`.`lastMessageAt` DESC
', 'Conversation', 'iii', (int) $this -> userId, (int) $this -> userId, $not_banned);
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> contents === []) {
            $this -> contents[] = new Notice('You don\'t have any conversations yet.');
        }

        return parent::toDOM();
    }
}
