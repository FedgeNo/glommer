<?php

declare(strict_types=1);

class ImageItem extends FeedItem
{
    public ?string $itemType = self::class;

    public function toDOM(): \DOMElement
    {
        $image = new Image();
        $image -> alt = $this -> altText ?? 'Image';

        if ($this -> deferred) {
            $image -> attributes['data-src'] = $this -> srcURL();
        } else {
            $image -> src = $this -> srcURL();
        }

        $this -> contents[] = $image;

        return parent::toDOM();
    }
}
