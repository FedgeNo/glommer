<?php

declare(strict_types=1);

class NotificationsNavLink extends Div
{
    public ?string $class = 'NotificationsNavLink';

    /** @var array[] */
    public array $rows;

    public int $lastNotificationId;

    public function __construct(array $rows, int $last_notification_id)
    {
        parent::__construct();

        $this -> rows = $rows;
        $this -> lastNotificationId = $last_notification_id;
    }

    public function toDOM(): \DOMElement
    {
        // The newest of the (already newest-first) recent rows, if any -
        // main.js polls for anything created after this, and it's also all
        // that's needed to know whether there's something unseen right now.
        $newest_id = $this -> rows !== [] ? (int) $this -> rows[0]['notificationId'] : 0;
        $has_unseen = $newest_id > $this -> lastNotificationId;

        $this -> attributes['data-newest-notification-id'] = (string) $newest_id;

        $this -> addContents(new Anchor(URL::absolute('/notifications/'), 'Notifications'));

        $dot = new Span();
        $dot -> class = 'NotificationDot' . ($has_unseen ? ' Active' : '');
        $this -> addContents($dot);

        $this -> addContents(new NotificationDropdown($this -> rows));

        return parent::toDOM();
    }
}
