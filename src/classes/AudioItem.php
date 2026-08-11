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

        // Above the controls, so the bars sit between the post and the player
        // rather than pushing the controls around as they move.
        $this -> contents[] = new SpectrumAnalyser();
        $this -> contents[] = $audio;

        return parent::toDOM();
    }
}
