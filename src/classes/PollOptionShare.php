<?php

declare(strict_types=1);

/**
 * One option's result in words: the percentage, with the count behind it.
 *
 * Both, because neither alone is honest. A percentage without a count reads the
 * same whether three people answered or three thousand, and a count without a
 * percentage makes every poll a sum the reader has to do themselves.
 */
class PollOptionShare extends Span
{
    public ?string $class = 'PollOptionShare';

    public function __construct(int $share, int $votes)
    {
        parent::__construct();

        $this -> addContent($share . '% ');
        $this -> addContent(new PollOptionVotes($votes));
    }
}
