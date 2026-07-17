<?php

declare(strict_types=1);

class PostSearch extends HTMLObject
{
    public ?string $class = 'PostSearch';

    /**
     * $author_id scopes the search to one user's posts (the per-user search on a
     * profile page); null searches everyone (the global /search page). The
     * client reads data-user-id to pass ?userId to /api/search-posts.
     */
    public function __construct(private readonly ?int $authorId = null, private readonly string $placeholder = 'Search posts...')
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> authorId !== null) {
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

        $this -> contents[] = new PostSearchResults();

        return parent::toDOM();
    }
}
