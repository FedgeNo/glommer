<?php

declare(strict_types=1);

/**
 * The bottom-right "Autoplay" toggle on a multi-item carousel. main.js starts
 * auto-advancing when it's clicked and flips the label to "Stop Autoplay":
 * an image slide holds for a few seconds before moving on, a video/audio
 * slide plays through to its end first. Autoplay stops (reverting the label)
 * on the next/prev buttons, a click on an image, or the viewer manually
 * (re-)starting a video/audio themselves.
 */
class CarouselAutoplayButton extends Button
{
    public function __construct()
    {
        parent::__construct();

        $this -> type = 'button';
        $this -> class = 'CarouselAutoplay';
        $this -> contents[] = 'Autoplay';
    }
}
