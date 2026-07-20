<?php

declare(strict_types=1);

/**
 * One conversation's messages, oldest last so the newest sits at the bottom of
 * the thread. Grown upward by infinite scroll (main.js) off the data-*
 * attributes here. Build with new MessageList(['userId' => 5,
 * 'otherUserId' => 9]).
 */
class MessageList extends ItemList
{
    public ?string $class = 'MessageList d-flex flex-column';

    protected string $emptyNotice = 'No messages yet.';

    public ?int $userId = null;
    public ?int $otherUserId = null;

    protected function rows(): array
    {
        // The two directions run as separate UNION ALL halves rather than one
        // OR: each half walks its (senderId, recipientId, messageId) index
        // backward and stops at its limit, so only the merged rows ever get
        // sorted - an OR forces collecting and filesorting the whole
        // conversation before the LIMIT can apply. The outer OFFSET skips rows
        // from the merged set, so each half must produce every row up to
        // offset + limit for the page to be complete.
        $limit = static::PAGE_SIZE + 1;
        $half_limit = $this -> offset + $limit;
        $user_id = (int) $this -> userId;
        $other_id = (int) $this -> otherUserId;

        $rows = DB::rows('
(SELECT *
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
    ORDER BY `messageId` DESC
    LIMIT ?)
UNION ALL
(SELECT *
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
    ORDER BY `messageId` DESC
    LIMIT ?)
    ORDER BY `messageId` DESC
    LIMIT ? OFFSET ?
', 'Message', 'iiiiiiii', $user_id, $other_id, $half_limit, $other_id, $user_id, $half_limit, $limit, $this -> offset);

        $senders = User::loadMany(array_values(array_unique(array_map(
            static fn ($message): int => (int) $message -> senderId,
            $rows
        ))));

        foreach ($rows as $message) {
            $message -> sender = $senders[(int) $message -> senderId] ?? null;
        }

        return $rows;
    }

    /**
     * Newest last, so the thread reads downward even though the query walks
     * backward from the newest message to find the page.
     *
     * @param mixed[] $items
     * @return mixed[]
     */
    protected function arrange(array $items): array
    {
        return array_reverse($items);
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-other-user-id' => (string) $this -> otherUserId];
    }
}
