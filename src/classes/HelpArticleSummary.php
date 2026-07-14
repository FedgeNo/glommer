<?php

declare(strict_types=1);

/**
 * A tappable summary card for one article - its title and a one-line summary -
 * used in the Help index (grouped under categories) and in search results.
 * The whole card is a link to the article. Mirrored client-side in help.js.
 */
class HelpArticleSummary extends HTMLObject
{
    public string $tagName = 'a';
    public ?string $class = 'HelpArticleSummary Card';

    public function __construct(private readonly HelpArticle $article)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> attributes['href'] = $this -> article -> url();

        $this -> contents[] = new Heading3($this -> article -> title);

        $summary = new Paragraph($this -> article -> summary);
        $summary -> class = 'Muted';
        $this -> contents[] = $summary;

        return parent::toDOM();
    }
}
