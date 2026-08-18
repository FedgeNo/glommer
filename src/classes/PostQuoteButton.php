<?php

declare(strict_types=1);

/**
 * Opens the quote-composing page for a post - Repost's talkative sibling:
 * pass it on with words of your own above it. A link dressed as a button,
 * because composing happens on a page of its own.
 */
class PostQuoteButton extends Anchor
{
    public ?string $class = 'Button PostQuoteButton';

    public const GLYPH = '✍️';

    public function __construct(int $post_id)
    {
        parent::__construct(ServerURL::absolute('/quote/' . $post_id), self::GLYPH);

        // A link, so it has no ButtonButton to name it - and a glyph on its
        // own is nameless to anything not looking at it.
        $name = (string) (Strings::for(self::class)['name'] ?? '');
        $this -> attributes['aria-label'] = $name;
        $this -> attributes['title'] = $name;
    }
}
