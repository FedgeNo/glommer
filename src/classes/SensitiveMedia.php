<?php

declare(strict_types=1);

/**
 * The cover over a post whose media its author (or a moderator) classified as
 * sensitive. Opening it is the reader's decision, which is the entire point:
 * the post is still there, still readable, and the pictures wait until asked
 * for.
 *
 * A real <details> rather than an overlay a script uncovers, so it works before
 * any JavaScript loads and with none at all - a reader on a slow connection
 * gets the choice, not a blurred rectangle that never resolves. Keyboard
 * operation and the accessibility tree come with the element.
 *
 * Only media goes under here. The words stay visible, matching what the rest of
 * the network does with the same flag - a post nobody can read is a post nobody
 * can decide about.
 */
class SensitiveMedia extends Details
{
    public ?string $class = 'SensitiveMedia';

    public function toDOM(): \DOMElement
    {
        $summary = new Summary();
        $summary -> class = 'SensitiveMediaSummary';
        $summary -> contents[] = 'Sensitive media';

        // Prepended rather than appended: <summary> is only the disclosure
        // control when it's the element's first child.
        array_unshift($this -> contents, $summary);

        return parent::toDOM();
    }
}
