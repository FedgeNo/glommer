<?php

declare(strict_types=1);

/**
 * One option while it can still be chosen: the control and the text it belongs
 * to, wrapped in the label that ties them together so the whole row is a
 * target rather than just the box.
 */
class PollOptionControl extends Label
{
    public ?string $class = 'PollOptionControl';

    public function __construct(int $option_id, string $title, bool $multiple)
    {
        parent::__construct();

        $this -> addContent(new PollVoteInput($option_id, $multiple));
        $this -> addContent(new PollOptionTitle($title));
    }
}
