<?php

declare(strict_types=1);

/**
 * Renders a user's avatar image, or - when they haven't uploaded one - a
 * pure CSS/text fallback: a circle in a color derived from their userId,
 * containing the first letter of their name. No image request, no canvas,
 * just markup.
 */
class Avatar extends HTMLObject
{
    public string $tagName = 'img';

    public bool $hasImage = false;
    public ?string $imageURL = null;
    public ?string $name = null;
    public int $userId = 0;
    public bool $small = false;

    public function toDOM(): \DOMElement
    {
        $size_class = $this -> small ? 'AvatarSm' : 'Avatar';

        if ($this -> hasImage && $this -> imageURL !== null) {
            $this -> tagName = 'img';
            $this -> class = $size_class;
            $this -> attributes['src'] = $this -> imageURL;
            $this -> attributes['alt'] = $this -> name . '\'s avatar';

            return parent::toDOM();
        }

        $this -> tagName = 'div';
        $this -> class = $size_class . ' AvatarInitial';
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

    public static function forUser(?User $user, bool $small = false): self
    {
        $avatar = new self();

        if ($user === null) {
            return $avatar;
        }

        $avatar -> hasImage = (bool) $user -> hasAvatar;
        $avatar -> imageURL = $user -> avatarURL();
        $avatar -> name = $user -> displayName ?? $user -> username;
        $avatar -> userId = (int) $user -> userId;
        $avatar -> small = $small;

        return $avatar;
    }
}
