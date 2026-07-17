<?php

declare(strict_types=1);

class VideoItem extends FeedItem
{
    public ?string $type = self::class;

    public function toDOM(): \DOMElement
    {
        $video = new Video();
        $video -> attributes['controls'] = 'controls';

        if ($this -> deferred) {
            $video -> attributes['data-src'] = $this -> srcURL();

            if ($this -> imageURL() !== null) {
                $video -> attributes['data-poster'] = $this -> imageURL();
            }
        } else {
            $video -> src = $this -> srcURL();
            $video -> attributes['poster'] = $this -> imageURL();
        }

        $this -> contents[] = $video;

        return parent::toDOM();
    }
}
