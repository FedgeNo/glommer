<?php

declare(strict_types=1);

class UserSearch extends HTMLObject
{
    public ?string $class = 'UserSearch';

    public function toDOM(): \DOMElement
    {
        $input_card = new Div();
        $input_card -> class = 'UserSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'UserSearchInput';
        $input -> attributes['placeholder'] = 'Search for a user...';
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContents($input);

        $this -> contents[] = $input_card;

        $results = new Div();
        $results -> class = 'UserSearchResults';
        $this -> contents[] = $results;

        return parent::toDOM();
    }
}
