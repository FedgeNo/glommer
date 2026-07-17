<?php

declare(strict_types=1);

/**
 * The pencil beside your own display name. It doesn't submit anything on its
 * own - clicking it (or the name, or the bio) flips the card into edit mode
 * client-side (see main.js). It's the visible "you can edit this" affordance.
 */
class EditProfileButton extends Button
{
    public ?string $class = 'EditProfileButton';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['aria-label'] = 'Edit profile';
        $this -> contents[] = '✏️';

        return parent::toDOM();
    }
}
