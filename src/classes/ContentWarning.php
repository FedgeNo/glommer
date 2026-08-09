<?php

declare(strict_types=1);

/**
 * The gate in front of a post whose author wrote a warning for it.
 *
 * Everything the author wrote goes behind this - words, media and poll alike -
 * which is the difference between it and SensitiveMedia. That covers pictures
 * and leaves the writing readable, because the flag it answers to says the
 * media is the thing to opt into. A warning is different: the commonest one on
 * the network is a spoiler, and what it is spoiling is the text. Covering the
 * media and printing the spoiler underneath honours the letter of the warning
 * and defeats its entire purpose.
 *
 * A real <details>, like SensitiveMedia, so the choice exists before any
 * JavaScript loads and with none at all, and so keyboard operation and the
 * accessibility tree come with the element rather than being rebuilt on top of
 * a div.
 *
 * No reader preference opens these by default. The media cover has one because
 * a reader can reasonably say "show me that kind of picture"; a warning is a
 * sentence written by a person about this post in particular, and there is no
 * standing answer to a thing nobody has read yet.
 */
class ContentWarning extends Details
{
    public ?string $class = 'ContentWarning';

    public function __construct(private readonly string $warning)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $summary = new ContentWarningSummary();
        $summary -> contents[] = $this -> warning;

        // Prepended rather than appended: <summary> is only the disclosure
        // control when it's the element's first child.
        array_unshift($this -> contents, $summary);

        return parent::toDOM();
    }
}
