<?php

declare(strict_types=1);

/**
 * What a poll says about itself underneath the choices: how many people
 * answered, and how long is left to.
 *
 * The count is of people rather than of votes, because on a multiple-choice
 * poll those are different numbers and the option tallies already report the
 * second one.
 */
class PollTally extends Footer
{
    public ?string $class = 'PollTally';

    public function __construct(int $voters, string $ends_at, bool $closed)
    {
        parent::__construct();

        $this -> addContent($voters === 1 ? '1 person voted' : $voters . ' people voted');

        $when = new Time();
        $when -> class = 'PollDeadline';
        // The machine-readable form is UTC, since the column is; the words
        // beside it are the reader's to interpret.
        $when -> datetime = gmdate('c', (int) strtotime($ends_at));
        $when -> addContent($closed ? 'Final result' : 'Closes ' . self::remaining($ends_at));

        $this -> addContent($when);
    }

    /**
     * How long is left, in the largest unit that still says something useful.
     * "in 2 days" rather than "in 51 hours" - a poll's deadline is something a
     * reader judges at a glance, not a figure they need to the minute.
     */
    private static function remaining(string $ends_at): string
    {
        $seconds = max(0, (int) strtotime($ends_at) - time());

        foreach ([86400 => 'day', 3600 => 'hour', 60 => 'minute'] as $size => $unit) {
            if ($seconds >= $size) {
                $count = intdiv($seconds, $size);

                return 'in ' . $count . ' ' . ($count === 1 ? $unit : $unit . 's');
            }
        }

        return 'in under a minute';
    }
}
