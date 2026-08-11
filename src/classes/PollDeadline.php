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

    public function __construct(private readonly string $endsAt, private readonly bool $closed)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> datetime = gmdate('c', (int) strtotime($this -> endsAt));

        $words = Strings::for(self::class);

        if ($this -> closed) {
            $this -> addContent((string) ($words['final'] ?? ''));
        } else {
            $sentence = $words['closes'] ?? [];

            $this -> contents[] = (string) ($sentence['before'] ?? '');
            $this -> contents[] = self::remaining($this -> endsAt);
            $this -> contents[] = (string) ($sentence['after'] ?? '');
        }

        return parent::toDOM();
    }

    /**
     * How long is left, in the largest unit that still says something useful.
     * "in 2 days" rather than "in 51 hours" - a poll's deadline is something a
     * reader judges at a glance, not a figure they need to the minute.
     */
    private static function remaining(string $ends_at): string
    {
        $seconds = max(0, (int) strtotime($ends_at) - time());

        foreach ([86400 => 'days', 3600 => 'hours', 60 => 'minutes'] as $size => $key) {
            if ($seconds >= $size) {
                $count = intdiv($seconds, $size);

                return str_replace('{count}', (string) $count, Strings::plural(self::class, $key, $count));
            }
        }

        return (string) (Strings::for(self::class)['underMinute'] ?? '');
    }
}
