<?php

declare(strict_types=1);

class LinkItem extends FeedItem
{
    public ?string $linkURL = null;
    public ?string $description = null;

    public function __construct(string $link_url, ?string $description = null)
    {
        parent::__construct();

        $this -> linkURL = $link_url;
        $this -> description = $description;
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> description !== null) {
            $body = new PostBody();
            $body -> addContents($this -> description);
            $this -> contents[] = $body;
        }

        $this -> contents[] = new Anchor($this -> linkURL, $this -> linkURL);

        return parent::toDOM();
    }
}
