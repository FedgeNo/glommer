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

            // Remote media has no thumbnail here and never will - it isn't
            // ours to transcode (see FeedItem::imageURL()) - so a video from
            // another server plays without a poster rather than carrying an
            // empty one.
            if ($poster !== null) {
                $video -> attributes['poster'] = $poster;
            }
        }

        $this -> contents[] = $video;

        return parent::toDOM();
    }
}
