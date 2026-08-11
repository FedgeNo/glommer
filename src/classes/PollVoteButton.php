<?php

declare(strict_types=1);

/**
 * Casts the reader's choices. One button for the whole poll rather than one per
 * option, because a multiple-choice poll is a single answer made of several
 * picks - sending each tick separately would make a half-finished answer
 * indistinguishable from a finished one.
 */
class PollVoteButton extends ButtonButton
{
    public function __construct(int $poll_id)
    {
        parent::__construct();

        $this -> attributes['type'] = 'button';
        $this -> attributes['data-poll-id'] = (string) $poll_id;
        $this -> addContent((string) (Strings::for(self::class)['name'] ?? ''));
    }
}
