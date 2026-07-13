<?php

declare(strict_types=1);

class PostSearch extends HTMLObject
{
    public ?string $class = 'PostSearch';

    public function toDOM(): \DOMElement
    {
        $input_card = new Div();
        $input_card -> class = 'PostSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'PostSearchInput';
        $input -> attributes['placeholder'] = 'Search posts...';
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        $results = new Div();
        $results -> class = 'PostSearchResults d-flex flex-column gap-4';
        // Empty until a query is typed - there's no "suggestions" state for
        // post search the way UserSearch has one, so nothing to paginate yet.
        $results -> attributes['data-has-more'] = '0';

        $this -> contents[] = $results;

        return parent::toDOM();
    }
}
