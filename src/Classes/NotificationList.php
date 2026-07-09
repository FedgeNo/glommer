<?php

declare(strict_types=1);

class NotificationList extends Div
{
    public ?string $class = 'NotificationList d-flex flex-column gap-1';

    public ?int $oldestNotificationId = null;
    public bool $hasMore = false;

    public function toDOM(): \DOMElement
    {
        if ($this -> oldestNotificationId !== null) {
            $this -> attributes['data-oldest-notification-id'] = (string) $this -> oldestNotificationId;
        }

        $this -> attributes['data-has-more'] = $this -> hasMore ? '1' : '0';

        return parent::toDOM();
    }

    /**
     * @param array[] $rows Notification rows, newest first.
     */
    public static function fromRows(array $rows, bool $has_more): self
    {
        $list = new self();

        if ($rows === []) {
            $list -> addContents(new Notice('No notifications yet.'));

            return $list;
        }

        $list -> oldestNotificationId = (int) $rows[count($rows) - 1]['notificationId'];
        $list -> hasMore = $has_more;

        foreach ($rows as $row) {
            $list -> addContents(Notification::fromRow($row));
        }

        return $list;
    }
}
