<?php

declare(strict_types=1);

/**
 * How many people picked one option, in words rather than a bare number.
 *
 * The count chooses the phrasing rather than a ternary here choosing it:
 * English has two forms, Polish three, and no arrangement of `=== 1` in a
 * class produces the third. See Strings::plural().
 */
class PollOptionVotes extends Span
{
    public ?string $class = 'PollOptionVotes';

    public function __construct(private readonly int $votes)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> addContent(str_replace(
            '{count}',
            (string) $this -> votes,
            Strings::plural(self::class, 'votes', $this -> votes)
        ));

        return parent::toDOM();
    }
}
