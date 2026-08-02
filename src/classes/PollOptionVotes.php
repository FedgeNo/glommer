<?php

declare(strict_types=1);

/** How many people picked one option, in words rather than a bare number. */
class PollOptionVotes extends Span
{
    public ?string $class = 'PollOptionVotes';

    public function __construct(int $votes)
    {
        parent::__construct();

        $this -> addContent($votes === 1 ? '1 vote' : $votes . ' votes');
    }
}
