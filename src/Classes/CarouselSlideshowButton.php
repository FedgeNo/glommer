<?php

declare(strict_types=1);

/**
 * The bottom-right "Slideshow" toggle on a multi-item carousel. main.js starts
 * an auto-advancing slideshow when it's clicked and flips the label to
 * "Stop Slideshow"; the slideshow stops (reverting the label) on the next/prev
 * buttons, a click on an image, or a video being played.
 */
class CarouselSlideshowButton extends Button
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> class = 'CarouselSlideshow';
        $this -> contents[] = 'Slideshow';
    }
}
