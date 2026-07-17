<?php

declare(strict_types=1);

class AudioItem extends FeedItem
{
    public ?string $type = self::class;

    public function toDOM(): \DOMElement
    {
        $audio = new Audio();
        $audio -> attributes['controls'] = 'controls';

        if ($this -> deferred) {
            $audio -> attributes['data-src'] = $this -> srcURL();
        } else {
            $audio -> src = $this -> srcURL();
        }

        $this -> contents[] = $audio;

        return parent::toDOM();
    }
}
