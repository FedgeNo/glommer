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
 *
 * A reader can say once, in their settings, that they would rather see it -
 * shownByDefault() answers for the person looking, and a post they would have
 * had to open renders as an ordinary one.
 */
class SensitiveMedia extends Details
{
    public ?string $class = 'SensitiveMedia';

    /**
     * Whether the reader has asked to be shown this media without opening it.
     *
     * Off for a signed-out visitor as well as for anyone who has not chosen it:
     * the cover exists so that nobody meets media of this kind without having
     * decided to, and somebody with no account has made no such decision.
     */
    public static function shownByDefault(): bool
    {
        return (int) (Auth::user() ?-> showSensitiveMedia ?? 0) === 1;
    }

    public function toDOM(): \DOMElement
    {
        $summary = new SensitiveMediaSummary();
        $summary -> contents[] = (string) (Strings::for(self::class)['summary'] ?? '');

        // Prepended rather than appended: <summary> is only the disclosure
        // control when it's the element's first child.
        array_unshift($this -> contents, $summary);

        return parent::toDOM();
    }
}
