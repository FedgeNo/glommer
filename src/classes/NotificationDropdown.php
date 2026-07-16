<?php

declare(strict_types=1);

class NotificationDropdown extends Div
{
    public ?string $class = 'NotificationDropdown Card';

    public ?int $userId = null;

    private RecentNotificationList $list;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> list = new RecentNotificationList(['userId' => $this -> userId]);
    }

    /** The newest notification's id, for the nav's unseen dot. */
    public function newestId(): int
    {
        return $this -> list -> newestId();
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = $this -> list;
        $this -> contents[] = new Anchor(ServerURL::absolute('/notifications'), 'Show All');

        return parent::toDOM();
    }
}
