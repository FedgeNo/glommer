<?php

declare(strict_types=1);

// The shared shape of an OPML document: a <head><title> and a <body> of
// <outline>s a subclass supplies. OPMLIndex's outlines point at shards;
// OPMLShard's point at members' own RSS feeds.
abstract class OPMLDocument extends XMLDocument
{
    public string $tagName = 'opml';
    public string $contentType = 'text/x-opml; charset=UTF-8';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['version'] = '2.0';

        $head = new XMLObject();
        $head -> tagName = 'head';

        $title = new XMLObject();
        $title -> tagName = 'title';
        $title -> addContent($this -> title());
        $head -> addContent($title);

        $this -> contents[] = $head;

        $body = new XMLObject();
        $body -> tagName = 'body';

        foreach ($this -> outlines() as $outline) {
            $body -> addContent($outline);
        }

        $this -> contents[] = $body;

        return parent::toDOM();
    }

    abstract protected function title(): string;

    /** @return OPMLOutline[] */
    abstract protected function outlines(): array;
}
