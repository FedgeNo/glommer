<?php

declare(strict_types=1);

/**
 * A button whose words change, that does not change size when they do.
 *
 * A row where one button relabels itself shoves every neighbour along, and the
 * eye loses where it was - press Like and the four buttons after it jump. So
 * every wording the button can show is built into it at once, stacked in one
 * grid cell with the ones it is not showing hidden but still measured. It is
 * as wide as its longest wording from the moment it renders and stays there.
 *
 * Each button reserves its own longest label rather than the row sharing one
 * width: a common width has to fit the longest label anywhere in the row,
 * which turns "Pin" into a slab.
 *
 * The mirror of scripts/ToggleButton.js, which builds the same markup for a
 * card the client rendered - the two have to agree, since a feed holds both.
 */
class ToggleButton extends ButtonButton
{
    public ?string $class = 'ToggleButton';

    /**
     * Every wording this button can show, the first being the default.
     *
     * @var string[]
     */
    protected array $labels = [];

    /** Which of them it is showing. Null shows the first. */
    protected ?string $showing = null;

    /**
     * A wording never shown, there only to be measured.
     *
     * A label carrying a count cannot be listed in advance - the count is
     * whatever it is, and it changes under the reader as other people press
     * the same button. So the button reserves the width of the widest form it
     * expects instead, and the live label is rewritten inside that. Three
     * digits is the bargain: wide enough that few posts ever exceed it,
     * narrow enough that a button sized for it does not look padded.
     */
    protected ?string $reserve = null;

    /** The stand-in a count-carrying label reserves room for. */
    public const RESERVED_COUNT = '(XXX)';

    public function toDOM(): \DOMElement
    {
        $showing = $this -> showing ?? ($this -> labels[0] ?? '');

        foreach ($this -> labels as $label) {
            $this -> contents[] = self::labelSpan($label, $label !== $showing);
        }

        if ($this -> reserve !== null) {
            $reservation = self::labelSpan($this -> reserve, true);
            $reservation -> class .= ' ToggleButtonReservation';
            // Nothing should read this out: it is furniture holding a width,
            // not a second thing the button says.
            $reservation -> attributes['aria-hidden'] = 'true';

            $this -> contents[] = $reservation;
        }

        return parent::toDOM();
    }

    private static function labelSpan(string $text, bool $inactive): Span
    {
        $span = new Span();
        $span -> class = $inactive ? 'ToggleButtonLabel Inactive' : 'ToggleButtonLabel';
        $span -> contents[] = $text;

        return $span;
    }
}
