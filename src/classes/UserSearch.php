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
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        $this -> contents[] = new UserSearchSection();

        return parent::toDOM();
    }
}
