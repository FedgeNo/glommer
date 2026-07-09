<?php

declare(strict_types=1);

class ImageItem extends FeedItem
{
    public ?string $itemType = self::class;

    public function toDOM(): \DOMElement
    {
        $image = new Image();
        $image -> src = $this -> srcURL();
        $image -> alt = $this -> altText ?? 'Image';

        $this -> contents[] = $image;

        return parent::toDOM();
    }
}
