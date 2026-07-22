<?php

declare(strict_types=1);

/**
 * A syndication feed: channel metadata and a page of items it loads itself. A
 * subclass supplies its own selection in rows() and its own title/link/
 * description; this class holds the one rendering both share.
 *
 * Whatever the selection needs - whose profile the feed belongs to - is a
 * property seeded through the constructor (new UserRSSFeed(['user' => $u])),
 * never an argument, so the object is fully loaded the moment it exists.
 */
abstract class RSSFeed {
    /** How many entries a feed carries. */
    protected const LIMIT = 50;

    public string $title;
    public string $link;
    public string $description;

    /** @var RSSItem[] */
    public array $items = [];

    public function __construct(array|object|null $properties = null) {
        if ($properties !== null) {
            foreach (is_array($properties) ? $properties : get_object_vars($properties) as $name => $value) {
                if (property_exists($this, $name)) {
                    $this -> $name = $value;
                }
            }
        }

        $this -> items = $this -> rows();
    }

    /** @return RSSItem[] */
    abstract protected function rows(): array;

    public function toXML(): string {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document -> formatOutput = true;
        RSSItem::$document = $document;

        $rss = $document -> createElement('rss');
        $rss -> setAttribute('version', '2.0');
        $document -> appendChild($rss);

        $channel = $document -> createElement('channel');
        $rss -> appendChild($channel);

        $channel -> appendChild(RSSItem::textElement('title', $this -> title));
        $channel -> appendChild(RSSItem::textElement('link', $this -> link));
        $channel -> appendChild(RSSItem::textElement('description', $this -> description));

        foreach ($this -> items as $item) {
            $channel -> appendChild($item -> toElement());
        }

        return $document -> saveXML();
    }

    public function send(): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo $this -> toXML();
        exit;
    }
}
