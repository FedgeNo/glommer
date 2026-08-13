<?php

declare(strict_types=1);

/**
 * What somebody wrote, as they wrote it.
 *
 * Preformatted rather than a paragraph because the line breaks and the
 * indentation they typed are part of what was said - a list, an address, a
 * function body somebody wants to be able to run at the other end. That is the
 * element for text whose structure is carried by its own typography, so the
 * shape survives a reader with the stylesheet turned off, which a paragraph
 * held together by white-space alone does not.
 *
 * It keeps the site's own typeface and wraps like any other text: preformatted
 * is about the whitespace being meaningful, not about the words being code.
 */
class MessageBody extends Preformatted
{
    public ?string $class = 'MessageBody';
}
