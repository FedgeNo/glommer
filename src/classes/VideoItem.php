<?php

declare(strict_types=1);

class VideoItem extends FeedItem
{
    public ?string $type = self::class;

    public function toDOM(): \DOMElement
    {
        $video = new Video();
        $video -> attributes['controls'] = 'controls';

        $thumbPoster = $this -> imageURL();                    // thumbnail poster (e.g. 93-thumb.jpg)
        $fullPoster  = $this -> fullImageURL() ?? $thumbPoster; // full poster (e.g. 93-original.jpg), fallback to thumb

        if ($this -> deferred) {
            $video -> attributes['data-src'] = $this -> srcURL();
            if ($thumbPoster !== null) {
                $video -> attributes['data-poster'] = $thumbPoster;
            }
        } else {
            $video -> src = $this -> srcURL();
            $video -> attributes['poster'] = $thumbPoster;
        }

        // Always attach the full poster URL so fullscreen can upgrade it
        if ($fullPoster !== null) {
            $video -> attributes['data-poster-full-src'] = $fullPoster;
        }

        $this -> contents[] = $video;

        return parent::toDOM();
    }
}
