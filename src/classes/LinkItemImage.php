<?php

declare(strict_types=1);

class LinkItemImage extends Image
{
    use FeedMedia;

    protected const HYDRATION_EXCLUSIONS = ['id'];

    public ?string $class = 'LinkItemImage';
    public ?int $itemId = null;
    public ?string $type = 'ImageItem';
    public ?string $remoteURL = null;

    public function toDOM(): \DOMElement
    {
        $this -> src = $this -> imageURL();
        $this -> alt = (string) (Strings::for(LinkItem::class)['alt'] ?? '');

        return parent::toDOM();
    }
}
