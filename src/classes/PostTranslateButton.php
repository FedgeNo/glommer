<?php

declare(strict_types=1);

/**
 * Asks the server for this post's body in the reader's own language - the
 * fediverse and the relay feed deliver posts in every language there is, and
 * this is what makes them readable without leaving the page. Only rendered
 * when the post has body text and the server has a translator configured;
 * the click swaps the body in place and offers the original back.
 */
class PostTranslateButton extends ToggleButton
{
    public function __construct()
    {
        parent::__construct();

        // Both wordings from the start: pressing it swaps the body and the
        // button, and "Show original" is the wider of the two.
        $this -> labels = ['Translate', 'Show original'];
    }
}
