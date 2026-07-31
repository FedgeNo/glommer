<?php

declare(strict_types=1);

class VideoItem extends FeedItem
{
    public ?string $type = self::class;

    public function toDOM(): \DOMElement
    {
        $video = new Video();
        $video -> attributes['controls'] = 'controls';

        $poster = $this -> imageURL();

        if ($this -> deferred) {
            $video -> attributes['data-src'] = $this -> srcURL();

            if ($poster !== null) {
                $video -> attributes['data-poster'] = $poster;
            }
        } else {
            $video -> src = $this -> srcURL();
            $video -> attributes['poster'] = $poster;
        }

        $this -> contents[] = $video;

        return parent::toDOM();
    }
}
