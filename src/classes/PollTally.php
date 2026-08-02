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
        $this -> addContent(new PollDeadline($ends_at, $closed));
    }
}
