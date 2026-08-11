<?php

declare(strict_types=1);

class RelativeTime extends Time
{
    public string $createdAt;
    public string $fallbackFormat;

    public function __construct(string $created_at, string $fallback_format = 'F j, Y g:i A')
    {
        parent::__construct();

        $this -> class = 'RelativeTime';
        $this -> createdAt = $created_at;
        $this -> fallbackFormat = $fallback_format;
    }

    public function toDOM(): \DOMElement
    {
        $moment = strtotime($this -> createdAt);

        $this -> datetime = date(DATE_ATOM, $moment);
        $this -> contents[] = date($this -> fallbackFormat, $moment);

        return parent::toDOM();
    }
}
