<?php

declare(strict_types=1);

/**
 * A syndication feed: an <rss> document wrapping channel metadata and a page of
 * <item>s it loads itself. A subclass supplies its own selection in rows() and
 * its own title/link/description; this class holds the rendering both share and
 * the RSS content type.
 *
 * Whatever the selection needs - whose profile the feed belongs to - is a
 * property seeded through the constructor (new UserRSSFeed(['user' => $u])),
 * never an argument, so the object is fully loaded the moment it exists.
 */
abstract class RSSFeed extends XMLDocument
{
    /** How many entries a feed carries. */
    protected const LIMIT = 50;

    public string $tagName = 'rss';
    public string $contentType = 'application/rss+xml; charset=UTF-8';

    public string $title;
    public string $link;
    public string $description;

    /** @var RSSItem[] */
    public array $items = [];

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> items = $this -> rows();
    }

    /** @return RSSItem[] */
    abstract protected function rows(): array;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['version'] = '2.0';

        $channel = new XMLObject();
        $channel -> tagName = 'channel';

        foreach (['title' => $this -> title, 'link' => $this -> link, 'description' => $this -> description] as $tag => $text) {
            $element = new XMLObject();
            $element -> tagName = $tag;
            $element -> addContent($text);
            $channel -> addContent($element);
        }

        foreach ($this -> items as $item) {
            $channel -> addContent($item);
        }

        $this -> contents[] = $channel;

        return parent::toDOM();
    }
}
