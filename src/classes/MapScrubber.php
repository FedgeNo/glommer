<?php

declare(strict_types=1);

/**
 * The time control under the map: a slider from the first located post to now,
 * which replays where posting happened over the site's whole history.
 *
 * Two readings of the same slider, because they answer different questions.
 * Cumulative - the default - shows everything posted up to the handle, so
 * dragging forward watches the map fill in. Window shows only what was posted
 * around the handle, which finds bursts but is empty most of the time. The
 * label always spells out what is on screen, since a map of pins with no date
 * on it is just a map of pins.
 *
 * Rendered empty and hidden: the range depends on posts the client fetches, so
 * PostMap.js fills in the dates and reveals it once it knows there are any.
 */
class MapScrubber extends Div
{
    public ?string $class = 'Card MapScrubber d-flex flex-column gap-2';

    public function toDOM(): \DOMElement
    {
        $header = new Div;
        $header -> class = 'MapScrubberHeader d-flex align-items-center gap-2';

        $label = new Div;
        $label -> class = 'MapScrubberLabel';
        $header -> addContent($label);

        $play = new Button;
        $play -> class = 'Button MapScrubberPlay ms-auto';
        $play -> addContent('Play');
        $header -> addContent($play);

        $mode = new Div;
        $mode -> class = 'MapScrubberMode d-flex gap-1';

        foreach (['cumulative' => 'Up to then', 'window' => 'Just then'] as $value => $text) {
            $button = new Button;
            $button -> class = 'Button MapScrubberModeButton' . ($value === 'cumulative' ? ' Active' : '');
            $button -> attributes['data-mode'] = $value;
            $button -> addContent($text);
            $mode -> addContent($button);
        }

        $header -> addContent($mode);
        $this -> addContent($header);

        // Starts at the far right - "now" - so the map opens showing every post
        // and dragging back is what takes them away. Landing on a partial map
        // would look like posts were missing. The scale is arbitrary units
        // across the whole span rather than days, so the slider has the same
        // feel whether the site is a week or a decade old.
        $range = new RangeInput(0, 1000, 1000);
        $range -> class = 'MapScrubberRange';
        $range -> attributes['aria-label'] = 'Show posts up to a date';
        $this -> addContent($range);

        $bounds = new Div;
        $bounds -> class = 'MapScrubberBounds d-flex justify-content-between';

        $first = new Div;
        $first -> class = 'MapScrubberFirst muted';
        $bounds -> addContent($first);

        $last = new Div;
        $last -> class = 'MapScrubberLast muted';
        $bounds -> addContent($last);

        $this -> addContent($bounds);

        return parent::toDOM();
    }
}
