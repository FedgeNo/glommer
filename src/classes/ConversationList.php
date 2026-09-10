<?php

declare(strict_types=1);

/**
 * The /messages inbox: one Conversation per person the viewer has exchanged
 * messages with, most-recent first, fetched at render time. Build with
 * new ConversationList(['userId' => 5]).
 */
class ConversationList extends ItemList
{
    public ?string $class = 'ConversationList';

    public ?int $userId = null;

    protected string $emptyNotice = '';

    public function __construct(array|object|null $properties = null)
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? '');
        parent::__construct($properties);
    }

    protected function rows(): array
    {
        $not_banned = 0;

        // Message writes maintain one ordered entry per partner. History is
        // consulted only when the latest message is removed or during upgrade.
        return DB::rows('
SELECT `u`.`userId`, `u`.`slug`, `u`.`title`, `u`.`hasAvatar`, `m`.`createdAt` AS `lastMessageAt`
    FROM `Conversations` `partners`
    JOIN `Messages` `m` ON `m`.`messageId` = `partners`.`lastMessageId`
    JOIN `Users` `u` ON `u`.`userId` = `partners`.`partnerId`
    WHERE `partners`.`userId` = ? AND `u`.`banned` = ?
    ORDER BY `partners`.`lastMessageId` DESC
    LIMIT ? OFFSET ?
', 'Conversation', 'iiii', (int) $this -> userId, $not_banned, static::PAGE_SIZE + 1, $this -> offset);
    }
}
