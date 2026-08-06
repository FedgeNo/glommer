<?php

declare(strict_types=1);

class ImageItem extends FeedItem
{
    public ?string $type = self::class;

    public function toDOM(): \DOMElement
    {
        // The raw value, distinct from the img's alt attribute below, which
        // falls back to "Image" - the editor reads this to prefill its alt
        // field, and a fallback read back as the author's words would be one.
        if ($this -> altText !== null && $this -> altText !== '') {
            $this -> attributes['data-alt-text'] = $this -> altText;
        }

        $image = new Image();
        $image -> alt = $this -> altText ?? 'Image';
        $image -> attributes['loading'] = 'lazy';
        $image -> attributes['decoding'] = 'async';

        $fullURL = $this -> srcURL();
        $thumbURL = $this -> imageURL() ?? $fullURL;

        if ($this -> deferred) {
            $image -> attributes['data-src'] = $thumbURL;
        } else {
            $image -> src = $thumbURL;
        }
        $image -> attributes['data-full-src'] = $fullURL;

        $this -> contents[] = $image;

        return parent::toDOM();
    }
}
