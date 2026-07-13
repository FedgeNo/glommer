<?php

declare(strict_types=1);

class LinkItem extends FeedItem
{
    public ?string $linkURL = null;
    public ?string $title = null;
    public ?string $description = null;
    public ?ImageItem $image = null;

    public function __construct(string $link_url, ?string $title = null, ?string $description = null, ?ImageItem $image = null)
    {
        parent::__construct();

        $this -> linkURL = $link_url;
        $this -> title = $title;
        $this -> description = $description;
        $this -> image = $image;
    }

    public function toDOM(): \DOMElement
    {
        $link = new Anchor($this -> linkURL);
        // Opens in a new tab; rel=noopener keeps the opened (user-submitted)
        // page from reaching back through window.opener.
        $link -> attributes['target'] = '_blank';
        $link -> attributes['rel'] = 'noopener';

        if ($this -> image !== null) {
            $image = new Image();
            $image -> class = 'LinkItemImage';
            $image -> src = $this -> image -> imageURL();
            $image -> alt = 'Link preview image';
            $link -> addContent($image);
        }

        $text = new Div();
        $text -> class = 'LinkItemText';

        if ($this -> title !== null) {
            $heading = new Heading3();
            $heading -> contents[] = $this -> title;
            $text -> addContent($heading);
        }

        // The description is plaintext (Posts.description) - a link card shows a
        // flat summary, never rich text, so it's a text node in a .PostBody div
        // (mirroring the client's linkItemToElement). It's already null or
        // non-empty (Delta::plainText trims and blanks empties), so no HTML
        // blank-check is needed.
        if ($this -> description !== null && $this -> description !== '') {
            $body = new Div();
            $body -> class = 'PostBody';
            $body -> addContent($this -> description);
            $text -> addContent($body);
        }

        $text -> addContent($this -> linkURL);

        $link -> addContent($text);

        $this -> contents[] = $link;

        return parent::toDOM();
    }
}
