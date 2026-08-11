<?php

declare(strict_types=1);

/**
 * When a poll closes, or that it already has.
 *
 * The remaining time is a <time> of its own inside the sentence rather than a
 * <time> wrapped around the whole of it: only the part that names an instant is
 * machine-readable, and the words either side are ordinary prose. It sits
 * between two text nodes for the reason an inline link does - a language is
 * free to put the deadline anywhere in the phrasing, or at the front of it.
 *
 * A closed poll says only that its result is final, which names no instant, so
 * there is no <time> in that phrasing at all.
 */
class PollDeadline extends Span
{
    public ?string $class = 'PollDeadline';

    public function __construct(private readonly string $endsAt, private readonly bool $closed)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        if ($this -> closed) {
            $this -> contents[] = (string) ($words['final'] ?? '');

            return parent::toDOM();
        }

        $sentence = $words['closes'] ?? [];

        // The instant is UTC because the column is; the words beside it are the
        // reader's to interpret.
        $remaining = new Time();
        $remaining -> datetime = gmdate('c', (int) strtotime($this -> endsAt));
        $remaining -> contents[] = self::remaining($this -> endsAt);

        $this -> contents[] = (string) ($sentence['before'] ?? '');
        $this -> addContent($remaining);
        $this -> contents[] = (string) ($sentence['after'] ?? '');

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
