<?php

declare(strict_types=1);

class RelativeTime extends Time
{
    public function __construct(string $created_at, string $fallback_format = 'F j, Y g:i A')
    {
        parent::__construct();

        $this -> class = 'RelativeTime';
        $this -> datetime = date(DATE_ATOM, strtotime($created_at));
        $this -> contents[] = date($fallback_format, strtotime($created_at));
    }
}
