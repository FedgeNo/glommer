<?php

declare(strict_types=1);

/**
 * The search box on the admin Banned Users page - same shape as UserSearch on
 * /users/, but its input filters the banned list: main.js fetches
 * /api/search-banned-users as you type and repopulates the BannedUserList
 * below (restoring the paginated first page when the box empties).
 */
class BannedUserSearch extends HTMLObject
{
    public ?string $class = 'BannedUserSearch';

    public function toDOM(): \DOMElement
    {
        $input_card = new Div();
        $input_card -> class = 'BannedUserSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'BannedUserSearchInput';
        $input -> attributes['placeholder'] = 'Search banned users...';
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        return parent::toDOM();
    }
}
