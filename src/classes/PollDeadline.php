<?php

declare(strict_types=1);

/**
 * When a poll closes, or that it already has.
 *
 * A <time> carrying the machine-readable instant, so the deadline is something
 * a reader's own tools can act on rather than only a phrase. The attribute is
 * UTC because the column is; the words beside it are the reader's to interpret.
 */
class PollDeadline extends Time
{
    public ?string $class = 'PollDeadline';

    public function __construct(string $ends_at, bool $closed)
    {
        parent::__construct();

        $this -> datetime = gmdate('c', (int) strtotime($ends_at));
        $this -> addContent($closed ? 'Final result' : 'Closes ' . self::remaining($ends_at));
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
