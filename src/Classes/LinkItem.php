<?php

declare(strict_types=1);

class LinkItem extends FeedItem
{
    public ?string $linkURL = null;
    public ?string $title = null;
    public ?string $description = null;

    public function __construct(string $link_url, ?string $title = null, ?string $description = null)
    {
        parent::__construct();

        $this -> linkURL = $link_url;
        $this -> title = $title;
        $this -> description = $description;
    }

    public function toDOM(): \DOMElement
    {
        $link = new Anchor($this -> linkURL);

        if ($this -> title !== null) {
            $heading = new Heading3();
            $heading -> contents[] = $this -> title;
            $link -> addContents($heading);
        }

        if ($this -> description !== null) {
            $body = new PostBody();
            $body -> addContents($this -> description);
            $link -> addContents($body);
        }

        $link -> addContents($this -> linkURL);

        $this -> contents[] = $link;

        return parent::toDOM();
    }
}
