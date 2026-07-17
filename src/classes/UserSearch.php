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

        $this -> contents[] = new UserSearchResults($this -> suggestions);

        return parent::toDOM();
    }
}
