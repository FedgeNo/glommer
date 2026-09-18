<?php

declare(strict_types=1);

class LinkItem extends FeedItem
{
    protected const HYDRATION_EXCLUSIONS = ['id'];

    public ?string $linkURL = null;
    public ?string $title = null;
    public ?string $description = null;
    /** @var FeedItem[] */
    public array $items = [];

    public function toDOM(): \DOMElement
    {
        // Defense-in-depth alongside the write-time linkURL validation
        // (create/edit-post.php): mirrors DeltaRenderer's link-rendering gate
        // so nothing server-rendered ever emits an unsafe-scheme href.
        $link = new Anchor(DeltaRenderer::isSafeLink((string) $this -> linkURL) ? $this -> linkURL : null);
        // Opens in a new tab; rel=noopener keeps the opened (user-submitted)
        // page from reaching back through window.opener.
        $link -> attributes['target'] = '_blank';
        $link -> attributes['rel'] = 'noopener';

        foreach ($this -> items as $item) {
            if ($item instanceof ImageItem) {
                $link -> addContent(new LinkItemImage($item));
                break;
            }
        }

        $text = new LinkItemText();

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
