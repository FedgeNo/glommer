<?php

declare(strict_types=1);

class MessageList extends ItemList
{
    public ?string $class = 'MessageList d-flex flex-column';

    public ?int $otherUserId = null;
    public ?int $oldestMessageId = null;
    public bool $hasMore = false;

    public function toDOM(): \DOMElement
    {
        if ($this -> otherUserId !== null) {
            $this -> attributes['data-other-user-id'] = (string) $this -> otherUserId;
        }

        if ($this -> oldestMessageId !== null) {
            $this -> attributes['data-oldest-message-id'] = (string) $this -> oldestMessageId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }

    /**
     * @param Message[] $rows oldest first.
     */
    public static function fromRows(int $other_user_id, array $rows, bool $has_more): self
    {
        $list = new self();
        $list -> otherUserId = $other_user_id;

        if ($rows === []) {
            $list -> addContent(new Notice('No messages yet.'));

            return $list;
        }

        $list -> oldestMessageId = (int) $rows[0] -> messageId;
        $list -> hasMore = $has_more;

        $sender_ids = array_values(array_unique(array_map(fn ($message) => (int) $message -> senderId, $rows)));
        $senders = User::loadMany($sender_ids);

        foreach ($rows as $message) {
            $message -> sender = $senders[(int) $message -> senderId] ?? null;
            $list -> addContent($message);
        }

        return $list;
    }
}
