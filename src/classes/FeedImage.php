<?php

declare(strict_types=1);

/** The image element owns thumbnail selection, deferred loading, and alt text. */
class FeedImage extends Image
{
    use FeedMedia;

    protected const HYDRATION_EXCLUSIONS = ['id'];

    public ?int $itemId = null;
    public ?string $type = 'ImageItem';
    public ?string $remoteURL = null;
    public ?string $altText = null;
    public bool $deferred = false;

    public function toDOM(): \DOMElement
    {
        $this -> alt = $this -> altText ?? (string) (Strings::for(ImageItem::class)['alt'] ?? '');
        $this -> attributes['loading'] = 'lazy';
        $this -> attributes['decoding'] = 'async';

        $full_url = $this -> srcURL();
        $thumbnail = $this -> imageURL() ?? $full_url;

        if ($this -> deferred) {
            $this -> src = null;
            $this -> attributes['data-src'] = $thumbnail;
        } else {
            $this -> src = $thumbnail;
        }

        $this -> attributes['data-full-src'] = $full_url;

        return parent::toDOM();
    }
}
