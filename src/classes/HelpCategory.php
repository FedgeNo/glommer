<?php

declare(strict_types=1);

/**
 * A category section on the Help index: a heading followed by the summary
 * cards of the articles in it. Mirrored client-side by HelpSearch in Controllers.js (the browse
 * view shown when the search box is empty).
 */
class HelpCategory extends Section
{
    public ?string $class = 'HelpCategory';

    /**
     * @param HelpArticle[] $articles
     */
    public function __construct(private readonly string $name, private readonly array $articles)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new Heading2($this -> name);

        $list = new HelpArticleList();

        foreach ($this -> articles as $article) {
            $list -> addContent(new HelpArticleSummary($article));
        }

        $this -> contents[] = $list;

        return parent::toDOM();
    }
}
