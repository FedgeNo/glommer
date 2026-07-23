<?php

declare(strict_types=1);

/**
 * One <item> in a feed, hydrated straight from the database by its feed's
 * rows(): the post's id and its author's slug (which together form the
 * permalink), its title and plaintext description, and when it was created.
 * link and pubDate are derived from those the moment the row loads, so an item
 * is a complete element the instant it exists.
 */
class RSSItem extends XMLObject
{
    public string $tagName = 'item';

    public ?int $postId = null;
    public ?string $authorSlug = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $createdAt = null;

    public string $link;
    public string $pubDate;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> link = ServerURL::absolute('/users/' . ($this -> authorSlug ?? '') . '/' . $this -> postId);
        $this -> pubDate = date(DATE_RSS, strtotime((string) $this -> createdAt));
    }

    public function toDOM(): \DOMElement
    {
        foreach ([
            'title' => $this -> displayTitle(),
            'link' => $this -> link,
            'description' => (string) $this -> description,
            'pubDate' => $this -> pubDate,
        ] as $tag => $text) {
            $element = new XMLObject();
            $element -> tagName = $tag;
            $element -> addContent($text);
            $this -> contents[] = $element;
        }

        $guid = new XMLObject();
        $guid -> tagName = 'guid';
        $guid -> attributes['isPermaLink'] = 'true';
        $guid -> addContent($this -> link);
        $this -> contents[] = $guid;

        return parent::toDOM();
    }

    /**
     * A post carries its own title when it has one; otherwise the feed shows a
     * short single-line summary of its description, or a placeholder when it
     * has neither.
     */
    private function displayTitle(): string
    {
        if ($this -> title !== null) {
            return $this -> title;
        }

        if ($this -> description !== null) {
            return truncate(trim(preg_replace('/\s+/', ' ', $this -> description)), 160);
        }

        return 'Untitled';
    }
}
