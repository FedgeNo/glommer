<?php

declare(strict_types=1);

class NotificationDropdown extends Div
{
    public ?string $class = 'NotificationDropdown Card';

    /** @var array[] */
    public array $rows;

    public function __construct(array $rows)
    {
        parent::__construct();

        $this -> rows = $rows;
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = NotificationList::fromRows($this -> rows, false);
        $this -> contents[] = new Anchor(ServerURL::absolute('/notifications'), 'Show All');

        return parent::toDOM();
    }
}
