<?php

declare(strict_types=1);

class PostSearch extends HTMLObject
{
    public ?string $class = 'PostSearch';

    /**
     * Scopes the search to one profile's posts; 0 searches everyone. The client
     * reads data-user-id to pass ?userId to /api/search-posts.
     */
    public int $authorId = 0;

    public string $placeholder = 'Search posts...';

    public function toDOM(): \DOMElement
    {
        if ($this -> authorId !== 0) {
            $this -> attributes['data-user-id'] = (string) $this -> authorId;
        }

        $input_card = new Div();
        $input_card -> class = 'PostSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'PostSearchInput';
        $input -> attributes['placeholder'] = $this -> placeholder;
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        $this -> contents[] = new SearchFeedList(['authorId' => $this -> authorId]);

        return parent::toDOM();
    }
}
