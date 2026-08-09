<?php

declare(strict_types=1);

/**
 * Pins one of your own posts to the top of your profile, or unpins it. One
 * button in two states rather than two buttons, since it is one decision.
 */
class PostPinButton extends ButtonButton
{
    private const GLYPH = '📌';

    public function __construct(bool $pinned)
    {
        parent::__construct();

        $this -> nameIt($pinned ? 'Unpin' : 'Pin');
        $this -> pressed($pinned);

        if ($pinned) {
            $this -> class .= ' Removing';
        }

        $this -> contents[] = self::GLYPH;
    }
}
