<?php

declare(strict_types=1);

/**
 * A permalink anchor showing a time as a RelativeTime - a post's timestamp in
 * its byline meta column.
 */
class TimestampLink extends Anchor
{
    public ?string $class = 'TimestampLink';

    public ?string $dateTime = null;

    public function __construct(?string $href = null, ?string $date_time = null)
    {
        parent::__construct($href);

        $this -> dateTime = $date_time;
    }

    public function toDOM(): \DOMElement
    {
        // The time it shows is the post's own createdAt, carried on the .Post
        // card - HTMLObjects.js stamps the datetime attribute from there.
        $time = new RelativeTime($this -> dateTime, RelativeTime::DATE_ONLY);
        $time -> datetime = null;

        $this -> contents[] = $time;

        return parent::toDOM();
    }
}
