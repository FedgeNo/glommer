<?php

declare(strict_types=1);

class VideoItem extends FeedItem
{
    public ?string $itemType = self::class;

    public function toDOM(): \DOMElement
    {
        $video = new Video();
        $video -> src = $this -> srcURL();
        $video -> attributes['poster'] = $this -> imageURL();
        $video -> attributes['controls'] = 'controls';

        $this -> contents[] = $video;

        return parent::toDOM();
    }
}
