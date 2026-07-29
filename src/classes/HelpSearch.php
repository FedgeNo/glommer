<?php

declare(strict_types=1);

/**
 * The Help index: a search box over a results area that starts out showing
 * every article grouped by category (the browse view). Typing swaps the area
 * for ranked matches, clearing it restores the browse view - all handled in
 * HelpSearch.js against /api/help-search, mirroring how UserSearch works.
 */
class HelpSearch extends Div
{
    public ?string $class = 'HelpSearch';

    public function toDOM(): \DOMElement
    {
        // The search landmark is the control itself, not the results below it.
        $input_card = new SearchLandmark();
        $input_card -> class = 'HelpSearchBox';
        $input_card -> mixins = ['Card'];

        $input = new TextInput();
        $input -> name = 'q';
        $input -> class = 'HelpSearchInput';
        $input -> attributes['placeholder'] = 'Search help…';
        $input -> attributes['autocomplete'] = 'off';
        $input_card -> addContent($input);

        $this -> contents[] = $input_card;

        $results = new Div();
        $results -> class = 'HelpSearchResults';

        foreach (HelpContent::groupedByCategory() as $name => $articles) {
            $results -> addContent(new HelpCategory($name, $articles));
        }

        $this -> contents[] = $results;

        return parent::toDOM();
    }
}
