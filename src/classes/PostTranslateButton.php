<?php

declare(strict_types=1);

/**
 * Asks the server for this post's body in the reader's own language - the
 * fediverse and the relay feed deliver posts in every language there is, and
 * this is what makes them readable without leaving the page. Only rendered
 * when the post has body text and the server has a translator configured;
 * the click swaps the body in place and offers the original back.
 *
 * Two glyphs, because the way back is a different act from the way there and
 * the button is all the reader has to tell them which one they are looking at.
 */
class PostTranslateButton extends ToggleButton
{
    public const TRANSLATE = '🌐';
    public const SHOW_ORIGINAL = '↩️';

    public function __construct()
    {
        parent::__construct();

        $this -> nameIt('Translate');
        $this -> labels = [self::TRANSLATE, self::SHOW_ORIGINAL];
    }
}
