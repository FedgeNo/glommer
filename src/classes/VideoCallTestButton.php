<?php

declare(strict_types=1);

class VideoCallTestButton extends ButtonButton
{
    public function toDOM(): \DOMElement
    {
        $this -> contents[] = (string) (Strings::for(self::class)['label'] ?? '');

        return parent::toDOM();
    }
}
