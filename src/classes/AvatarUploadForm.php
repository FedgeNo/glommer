<?php

declare(strict_types=1);

class AvatarUploadForm extends Form
{
    // No d-flex utility: its !important display would overpower the grid
    // the actions column lays this out with (see .CurrentUserActions).
    public ?string $class = 'AvatarUploadForm';

    public function toDOM(): \DOMElement
    {

        $file_input = new FileInput();
        $file_input -> name = 'avatar';
        $file_input -> attributes['accept'] = 'image/*';
        $this -> contents[] = $file_input;

        $this -> contents[] = new SubmitButton('Update Avatar');

        return parent::toDOM();
    }
}
