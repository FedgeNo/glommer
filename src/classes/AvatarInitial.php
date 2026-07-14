<?php

declare(strict_types=1);

/**
 * The fallback avatar for a user with no uploaded image: a <div> circle tinted
 * by a hue derived from their userId, holding the first letter of their name.
 * No image request - just markup and CSS.
 */
class AvatarInitial extends Avatar
{
    public string $tagName = 'div';
    public ?string $class = 'Avatar AvatarInitial';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['aria-hidden'] = 'true';
        $this -> attributes['style'] = '--avatar-hue: ' . ($this -> userId * 137 % 360) . 'deg';
        $this -> contents[] = $this -> initial();

        return parent::toDOM();
    }

    protected function initial(): string
    {
        $first_char = mb_substr((string) $this -> name, 0, 1);

        return $first_char !== '' ? mb_strtoupper($first_char) : '?';
    }
}
