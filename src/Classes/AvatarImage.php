<?php

declare(strict_types=1);

/**
 * The avatar of a user who has uploaded an image: a plain <img>.
 */
class AvatarImage extends Avatar
{
    public string $tagName = 'img';
    public ?string $class = 'Avatar';
    public ?string $imageURL = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['src'] = (string) $this -> imageURL;
        $this -> attributes['alt'] = $this -> name . '\'s avatar';

        return parent::toDOM();
    }
}
