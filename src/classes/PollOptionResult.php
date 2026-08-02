<?php

declare(strict_types=1);

/**
 * One option once it has been answered: the text, the bar, and the figures.
 *
 * The reader's own choice carries Chosen. Results replace the controls
 * entirely, so without it there would be nothing left on the page saying what
 * they picked.
 */
class PollOptionResult extends Div
{
    public ?string $class = 'PollOptionResult';

    public function __construct(string $title, int $share, int $votes, bool $chosen)
    {
        parent::__construct();

        if ($chosen) {
            $this -> class .= ' Chosen';
        }

        $this -> addContent(new PollOptionTitle($title));
        $this -> addContent(new PollOptionMeter($share));
        $this -> addContent(new PollOptionShare($share, $votes));
    }
}
