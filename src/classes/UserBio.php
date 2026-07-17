<?php

declare(strict_types=1);

/**
 * A user's bio: their plain-text description rendered with the same
 * URL/hashtag/mention linkifying a post's text gets (through DeltaRenderer, so
 * it shares Linkify's PHP/JS parity) - but never accepting Delta formatting.
 * Newlines are preserved by the .UserBio white-space rule, not <br>s.
 */
class UserBio extends HTMLObject
{
    public string $tagName = 'div';
    public ?string $class = 'UserBio';

    public ?string $description = null;

    public function toDOM(): \DOMElement
    {
        $element = parent::toDOM();

        foreach (DeltaRenderer::linkifyPlainText(self::currentDocument(), (string) $this -> description) as $node) {
            $element -> appendChild($node);
        }

        return $element;
    }
}
