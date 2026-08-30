<?php

declare(strict_types=1);

/**
 * The moving spectrum above an audio player - what the sound is made of right
 * now, while it plays.
 *
 * The present instant only, and deliberately so. Drawing the whole file would
 * mean decoding the whole file before a note is heard, where this taps the
 * graph the browser is already decoding for playback and costs one FFT window
 * of memory. Nothing is read ahead of the playhead because nothing has been
 * decoded ahead of it.
 *
 * Empty markup: it is a canvas, and everything in it is drawn by
 * scripts/Controllers.js once the reader presses play. A page where that
 * never runs shows nothing rather than a broken frame, which is why the height
 * belongs to the canvas and not to a wrapper.
 */
class SpectrumAnalyser extends Canvas
{
    public ?string $class = 'SpectrumAnalyser';

    /**
     * The drawing surface, in device-independent pixels. The script scales
     * these by the display's own ratio so the bars are not soft on a retina
     * screen; they are here so the element reserves its space before any
     * script has run and the player does not jump down the page.
     */
    public const WIDTH = 600;
    public const HEIGHT = 192;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['width'] = (string) self::WIDTH;
        $this -> attributes['height'] = (string) self::HEIGHT;

        // A decoration, not information: everything it shows is the sound
        // itself, which whoever is listening is already getting.
        $this -> attributes['aria-hidden'] = 'true';

        return parent::toDOM();
    }
}
