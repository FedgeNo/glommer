<?php

declare(strict_types=1);

class EmojiPickerTriggerButton extends ButtonButton
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> contents[] = '🙂';
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['aria-label'] = (string) (Strings::for(self::class)['label'] ?? '');

        return parent::toDOM();
    }
}
