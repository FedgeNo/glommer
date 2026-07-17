<?php

declare(strict_types=1);

/**
 * A user's notifications, fetched straight into its contents at construction
 * (new NotificationList(['userId' => 5])) and paginated by infinite scroll off
 * the data-* attributes toDOM sets. The nav's smaller, non-paginated variant is
 * RecentNotificationList, which just fixes a lower count.
 */
class NotificationList extends Div
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'NotificationList d-flex flex-column gap-1';

    public ?int $userId = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> contents = DB::rows('
SELECT `n`.*, `u`.`slug` AS `actorUsername`, `u`.`title` AS `actorDisplayName`, `u`.`hasAvatar` AS `actorHasAvatar`
    FROM `Notifications` `n`
    JOIN `Users` `u` ON `u`.`userId` = `n`.`actorId`
    WHERE `n`.`userId` = ?
    ORDER BY `n`.`notificationId` DESC
    LIMIT ?
', 'Notification', 'ii', (int) $this -> userId, static::PAGE_SIZE + 1);
    }

    /**
     * The newest notification's id, or 0 if there are none - the nav's unseen
     * dot compares this against the user's lastNotificationId.
     */
    public function newestId(): int
    {
        return $this -> contents !== [] ? (int) $this -> contents[0] -> notificationId : 0;
    }

    public function toDOM(): \DOMElement
    {
        // One extra was fetched so a leftover signals another page; drop it back
        // off once it's told us there's more.
        $has_more = count($this -> contents) > static::PAGE_SIZE;

        if ($has_more) {
            array_pop($this -> contents);
        }

        if ($this -> contents === []) {
            $this -> addContent(new Notice('No notifications yet.'));
        } else {
            $this -> attributes['data-oldest-notification-id'] = (string) $this -> contents[count($this -> contents) - 1] -> notificationId;
        }

        $this -> attributes['data-has-more'] = $has_more ? '1' : '0';

        return parent::toDOM();
    }
}
