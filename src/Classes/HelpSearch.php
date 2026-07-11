<?php

declare(strict_types=1);

/**
 * The Help index: a search box over a results area that starts out showing
 * every article grouped by category (the browse view). Typing swaps the area
 * for ranked matches, clearing it restores the browse view - all handled in
 * help.js against /api/help-search, mirroring how UserSearch works.
 */
class HelpSearch extends HTMLObject
{
    public ?string $class = 'HelpSearch';

    public function toDOM(): \DOMElement
    {
        $input_card = new Div();
        $input_card -> class = 'HelpSearchBox Card';

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'HelpSearchInput';
        $input -> attributes['placeholder'] = 'Search help...';
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContents($input);

        $this -> contents[] = $input_card;

        $results = new Div();
        $results -> class = 'HelpSearchResults';

        foreach (HelpContent::groupedByCategory() as $name => $articles) {
            $results -> addContents(new HelpCategory($name, $articles));
        }

        $this -> contents[] = $results;

        return parent::toDOM();
    }
}
