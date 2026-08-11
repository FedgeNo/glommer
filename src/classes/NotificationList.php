<?php

declare(strict_types=1);

/**
 * A user's notifications, fetched straight into its contents at construction
 * (new NotificationList(['userId' => 5])) and paginated by infinite scroll off
 * the data-* attributes toDOM sets. The nav's smaller, non-paginated variant is
 * RecentNotificationList, which just fixes a lower count.
 */
class NotificationList extends ItemList
{
    public const PAGE_SIZE = 20;

    public ?string $class = 'NotificationList';
    public array $mixins = ['d-flex', 'flex-column', 'gap-1'];

    public ?int $userId = null;

    // English, so anything that never calls rows() below still says something
    // sensible. Overwritten with this locale's own wording once rows()
    // actually runs, the same as UserSearchList::rows() overwrites it
    // dynamically for a reason of its own - a class-level default is fixed
    // the moment the object exists, before this class can ask Strings anything.
    protected string $emptyNotice = 'No notifications yet.';

    protected function rows(): array
    {
        $this -> emptyNotice = (string) (Strings::for(self::class)['emptyNotice'] ?? $this -> emptyNotice);

        return DB::rows('
SELECT `n`.*, `u`.`slug` AS `actorUsername`, `u`.`title` AS `actorDisplayName`, `u`.`hasAvatar` AS `actorHasAvatar`
    FROM `Notifications` `n`
    JOIN `Users` `u` ON `u`.`userId` = `n`.`actorId`
    WHERE `n`.`userId` = ?
    ORDER BY `n`.`notificationId` DESC
    LIMIT ? OFFSET ?
', 'Notification', 'iii', (int) $this -> userId, static::PAGE_SIZE + 1, $this -> offset);
    }

    /**
     * The newest notification's id, or 0 if there are none - the nav's unseen
     * dot compares this against the user's lastNotificationId.
     */
    public function newestId(): int
    {
        return $this -> items !== [] ? (int) $this -> items[0] -> notificationId : 0;
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-infinite-scroll' => (string) json_encode([
            'endpoint' => '/api/notification-history',
            'itemType' => 'Notification',
        ])];
    }
}

