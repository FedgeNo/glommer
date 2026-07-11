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
            $link -> addContents($image);
        }

        $text = new Div();
        $text -> class = 'LinkItemText';

        if ($this -> title !== null) {
            $heading = new Heading3();
            $heading -> contents[] = $this -> title;
            $text -> addContents($heading);
        }

        if (!self::isBlankDescription($this -> description)) {
            $body = new PostBody();
            $body -> addContents($this -> description);
            $text -> addContents($body);
        }

        $text -> addContents($this -> linkURL);

        $link -> addContents($text);

        $this -> contents[] = $link;

        return parent::toDOM();
    }

    /**
     * True for null, empty, and content that only looks non-empty because
     * of markup/whitespace - a Quill editor left "empty" typically still
     * saves as something like "<p><br></p>" rather than an empty string.
     */
    private static function isBlankDescription(?string $description): bool
    {
        if ($description === null) {
            return true;
        }

        $text = html_entity_decode(strip_tags($description), ENT_QUOTES);

        return preg_replace('/[\s\x{00A0}]+/u', '', $text) === '';
    }
}
