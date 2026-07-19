<?php

declare(strict_types=1);

/**
 * The top-right fullscreen toggle on a media post - a single item, or (when
 * added to a Carousel) the whole carousel including its prev/next/counter/
 * autoplay controls. main.js reuses this exact button element (never a
 * second one) when it moves the media into and out of a fullscreen overlay,
 * swapping the glyph/label in place rather than swapping elements.
 */
class MediaFullscreenButton extends Button
{
    public function __construct()
    {
        parent::__construct();

        $this -> class = 'MediaFullscreen';
        $this -> attributes['aria-label'] = 'Fullscreen';
        $this -> contents[] = '⛶';
    }
}
