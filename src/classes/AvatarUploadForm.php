<?php

declare(strict_types=1);

class AvatarUploadForm extends Form
{
    // Keep this component independent from the grid used by the surrounding
    // actions column (see .CurrentUserActions).
    public ?string $class = 'AvatarUploadForm';

    public function toDOM(): \DOMElement
    {

        $file_input = new FileInput();
        $file_input -> name = 'avatar';
        $file_input -> attributes['accept'] = 'image/*';
        $this -> contents[] = $file_input;

        $this -> contents[] = new SubmitButton((string) (Strings::for(self::class)['submit'] ?? ''));

        return parent::toDOM();
    }
}
