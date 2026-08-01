<?php

declare(strict_types=1);

/**
 * The text of one poll option, which is also its identity: a vote arriving from
 * another server names the option it chose by this string rather than by any
 * id, which is why no two options on a poll may read the same.
 */
class PollOptionTitle extends Span
{
    public ?string $class = 'PollOptionTitle';

    public function __construct(string $title)
    {
        parent::__construct();

        $this -> addContent($title);
    }
}
