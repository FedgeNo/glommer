<?php

declare(strict_types=1);

/**
 * The pass/fail chip above a suite's output. The verdict is part of the class
 * chain rather than an inline style, so the stylesheet colours it.
 */
class TestResultsBadge extends Div
{
    public ?string $class = 'TestResultsBadge';

    public function __construct(string $suite, bool $passing)
    {
        parent::__construct();

        $this -> class .= $passing ? ' TestResultsPass' : ' TestResultsFail';
        $this -> addContent($suite . ': ' . ($passing ? 'Passing' : 'Failing'));
    }
}
