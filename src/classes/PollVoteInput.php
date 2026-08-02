<?php

declare(strict_types=1);

/**
 * The control that picks one option.
 *
 * A radio when the poll takes one answer and a checkbox when it takes several,
 * which is the difference the browser already enforces - so a single-answer
 * poll cannot submit two without the page being tampered with. The server
 * checks again regardless.
 */
class PollVoteInput extends Input
{
    public ?string $class = 'PollVoteInput';

    public function __construct(int $option_id, bool $multiple)
    {
        parent::__construct();

        $this -> attributes['type'] = $multiple ? 'checkbox' : 'radio';
        // One name across the group, which is what makes a set of radios a
        // single choice rather than several independent ones.
        $this -> attributes['name'] = 'pollOption';
        $this -> attributes['value'] = (string) $option_id;
    }
}
