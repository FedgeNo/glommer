<?php

declare(strict_types=1);

class AudioItem extends FeedItem
{
    public ?string $itemType = self::class;

    public function toDOM(): \DOMElement
    {
        $audio = new Audio();
        $audio -> src = $this -> srcURL();
        $audio -> attributes['controls'] = 'controls';

        $this -> contents[] = $audio;

        return parent::toDOM();
    }
}
