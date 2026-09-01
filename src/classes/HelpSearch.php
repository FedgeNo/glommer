<?php

declare(strict_types=1);

/**
 * The Help index: a search box over a results area that starts out showing
 * every article grouped by category (the browse view). Typing swaps the area
 * for ranked matches, clearing it restores the browse view - the same
 * SearchBox/Controllers.js Search machinery every other search on the site runs on,
 * against /api/help-search.
 */
class HelpSearch extends Div
{
    public ?string $class = 'HelpSearch';

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new HelpSearchBox();

        $results = new HelpSearchResults();

        foreach (HelpContent::groupedByCategory() as $name => $articles) {
            $results -> addContent(new HelpCategory($name, $articles));
        }

        $this -> contents[] = $results;

        return parent::toDOM();
    }
}
