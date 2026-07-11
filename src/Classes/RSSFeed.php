<?php

declare(strict_types=1);

class RSSFeed
{
    public string $title;
    public string $link;
    public string $description;

    /** @var RSSItem[] */
    public array $items = [];

    public function __construct(string $title, string $link, string $description)
    {
        $this -> title = $title;
        $this -> link = $link;
        $this -> description = $description;
    }

    public function addItem(RSSItem $item): void
    {
        $this -> items[] = $item;
    }

    public function toXML(): string
    {
        $document = new \DOMDocument('1.0', 'UTF-8');
        $document -> formatOutput = true;
        RSSItem::$document = $document;

        $rss = $document -> createElement('rss');
        $rss -> setAttribute('version', '2.0');
        $document -> appendChild($rss);

        $channel = $document -> createElement('channel');
        $rss -> appendChild($channel);

        $channel -> appendChild($document -> createElement('title', $this -> title));
        $channel -> appendChild($document -> createElement('link', $this -> link));
        $channel -> appendChild($document -> createElement('description', $this -> description));

        foreach ($this -> items as $item) {
            $channel -> appendChild($item -> toElement());
        }

        return $document -> saveXML();
    }

    public function send(): void
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/rss+xml; charset=UTF-8');
        echo $this -> toXML();
        exit;
    }
}
