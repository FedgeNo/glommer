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

    public function __construct(private readonly int $voters, private readonly string $endsAt, private readonly bool $closed)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> addContent(str_replace(
            '{count}',
            (string) $this -> voters,
            Strings::plural(self::class, 'voters', $this -> voters)
        ));
        $this -> addContent(new PollDeadline($this -> endsAt, $this -> closed));

        return parent::toDOM();
    }
}
