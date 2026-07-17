<?php

declare(strict_types=1);

/**
 * A permalink anchor showing a time as a RelativeTime - a post's timestamp in
 * its byline meta column.
 */
class TimestampLink extends Anchor
{
    public ?string $class = 'TimestampLink Muted text-sm';

    public ?string $dateTime = null;

    public function __construct(?string $href = null, ?string $date_time = null)
    {
        parent::__construct($href);

        $this -> dateTime = $date_time;
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new RelativeTime($this -> dateTime, 'M j, Y');

        return parent::toDOM();
    }
}
