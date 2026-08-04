<?php

declare(strict_types=1);

class EmojiPickerTriggerButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> attributes['aria-label'] = 'Insert emoji';
        $this -> contents[] = '🙂';
    }
}
