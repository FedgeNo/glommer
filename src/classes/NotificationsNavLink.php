<?php

declare(strict_types=1);

class NotificationsNavLink extends Div
{
    public ?string $class = 'NotificationsNavLink';

    public int $userId;
    public int $lastNotificationId;

    public function __construct(int $user_id, int $last_notification_id)
    {
        parent::__construct();

        $this -> userId = $user_id;
        $this -> lastNotificationId = $last_notification_id;
    }

    public function toDOM(): \DOMElement
    {
        $dropdown = new NotificationDropdown(['userId' => $this -> userId]);

        // Its newest notification is all that's needed to know whether there's
        // something unseen right now.
        $has_unseen = $dropdown -> newestId() > $this -> lastNotificationId;

        $this -> addContent(new Anchor(ServerURL::absolute('/notifications'), 'Notifications'));

        $dot = new Span();
        $dot -> class = 'NotificationDot' . ($has_unseen ? ' Active' : '');
        $this -> addContent($dot);

        $this -> addContent($dropdown);

        return parent::toDOM();
    }
}
