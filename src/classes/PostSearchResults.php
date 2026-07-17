<?php

declare(strict_types=1);

/**
 * The results area of a PostSearch - a <ul> of post cards. Empty on the server
 * (post search has no pre-query suggestion state the way UserSearch does); the
 * client fills it with the matches for the current query (see main.js).
 */
class PostSearchResults extends ItemList
{
    public ?string $class = 'PostSearchResults';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-has-more'] = '0';

        return parent::toDOM();
    }
}
