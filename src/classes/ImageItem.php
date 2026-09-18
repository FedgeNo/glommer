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

        $this -> contents[] = new FeedImage($this);

        return parent::toDOM();
    }
}
