<?php

declare(strict_types=1);

class RSSLink extends Link
{
    public function __construct(string $href, string $title)
    {
        parent::__construct();

        $this -> rel = 'alternate';
        $this -> attributes['type'] = 'application/rss+xml';
        $this -> attributes['title'] = $title;
        $this -> href = $href;
    }
}
