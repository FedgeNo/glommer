<?php

declare(strict_types=1);

/**
 * One Help article. Authored in HelpContent (its source of truth); this class
 * carries the fields and renders the full article page. Summary cards in lists
 * and search results are HelpArticleSummary, not this.
 */
class HelpArticle extends HTMLObject
{
    public string $tagName = 'article';
    public ?string $class = 'HelpArticle';

    public function __construct(
        public readonly string $slug,
        public readonly string $title,
        public readonly string $category,
        public readonly string $summary,
        public readonly string $body,
    ) {
        parent::__construct();
    }

    public function url(): string
    {
        return ServerURL::absolute('/help/' . $this -> slug);
    }

    public function toDOM(): \DOMElement
    {
        $category_link = new Anchor(ServerURL::absolute('/help/'), $this -> category);
        $category_link -> class = 'HelpArticleCategory Muted text-sm';
        $this -> contents[] = $category_link;

        $this -> contents[] = new Heading3($this -> title);

        $body = new HelpArticleBody();
        $body -> contents[] = $this -> body;
        $this -> contents[] = $body;

        $back = new Anchor(ServerURL::absolute('/help/'), 'Back to all help');
        $back -> class = 'HelpBackLink';
        $this -> contents[] = $back;

        return parent::toDOM();
    }

    /**
     * @return array{slug: string, title: string, category: string, summary: string, url: string}
     */
    public function toPayload(): array
    {
        return [
            'slug' => $this -> slug,
            'title' => $this -> title,
            'category' => $this -> category,
            'summary' => $this -> summary,
            'url' => $this -> url(),
        ];
    }
}
