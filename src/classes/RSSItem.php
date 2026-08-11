<?php

declare(strict_types=1);

/**
 * One <item> in a feed, hydrated straight from the database by its feed's
 * rows(): the post's id and its author's slug (which together form the
 * permalink), its title and plaintext description, and when it was created.
 * The permalink and the RSS date are read off those where they are written,
 * so the item carries the row it came from rather than a second copy of it in
 * another shape.
 */
class RSSItem extends XMLObject
{
    public string $tagName = 'item';

    public ?int $postId = null;
    public ?string $authorSlug = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?string $contentWarning = null;
    public ?string $createdAt = null;

    public function toDOM(): \DOMElement
    {
        // A reader has no gate to put a warned post behind, so it carries its
        // warning in place of the writing rather than handing the whole thing
        // to whoever subscribed.
        $warned = (string) $this -> contentWarning !== '';

        foreach ([
            'title' => $this -> displayTitle(),
            'link' => $this -> link(),
            'description' => $warned ? (string) $this -> contentWarning : (string) $this -> description,
            'pubDate' => date(DATE_RSS, strtotime((string) $this -> createdAt)),
        ] as $tag => $text) {
            $element = new XMLObject();
            $element -> tagName = $tag;
            $element -> addContent($text);
            $this -> contents[] = $element;
        }

        $guid = new XMLObject();
        $guid -> tagName = 'guid';
        $guid -> attributes['isPermaLink'] = 'true';
        $guid -> addContent($this -> link());
        $this -> contents[] = $guid;

        return parent::toDOM();
    }

    public function link(): string
    {
        return ServerURL::absolute('/users/' . ($this -> authorSlug ?? '') . '/' . $this -> postId);
    }

    /**
     * A post carries its own title when it has one; otherwise the feed shows a
     * short single-line summary of its description, or a placeholder when it
     * has neither.
     */
    private function displayTitle(): string
    {
        // Ahead of the title, which sits behind the gate on the page as much
        // as the body does.
        if ((string) $this -> contentWarning !== '') {
            return (string) $this -> contentWarning;
        }

        if ($this -> title !== null) {
            return $this -> title;
        }

        if ($this -> description !== null) {
            return truncate(trim(preg_replace('/\s+/', ' ', $this -> description)), 160);
        }

        return 'Untitled';
    }
}
