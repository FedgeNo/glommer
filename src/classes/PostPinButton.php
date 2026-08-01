<?php

declare(strict_types=1);

/**
 * Pins one of your own posts to the top of your profile, or unpins it. One
 * button in two states rather than two buttons, since it is one decision.
 */
class PostPinButton extends ButtonButton
{
    public function __construct(bool $pinned)
    {
        parent::__construct();

        $this -> attributes['data-pinned'] = $pinned ? '1' : '0';
        $this -> contents[] = $pinned ? 'Unpin' : 'Pin';
    }
}
