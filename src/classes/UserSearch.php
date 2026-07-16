<?php

declare(strict_types=1);

class UserSearch extends HTMLObject
{
    public ?string $class = 'UserSearch';

    /**
     * @param OtherUser[] $suggestions shown in the results area before the
     *                                 user has typed anything
     */
    public function __construct(private readonly array $suggestions = [])
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $input_card = new Div();
        $input_card -> class = 'UserSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'UserSearchInput';
        $input -> attributes['placeholder'] = 'Search for a user...';
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        $results = new Div();
        $results -> class = 'UserSearchResults';
        // The suggestion list is a fixed, ranked set, not cursor-paginated
        // (see api/search-users.php) - infinite scroll only ever kicks in
        // once a typed query gets a paginated result set of its own.
        $results -> attributes['data-has-more'] = '0';

        $results -> addContents($this -> suggestions);

        $this -> contents[] = $results;

        return parent::toDOM();
    }
}
