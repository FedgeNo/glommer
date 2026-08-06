<?php

declare(strict_types=1);

class AvatarUploadForm extends Form
{
    // Stretch, not align-items-end: this lives in the profile card's actions
    // column, where every control shares the column's one width.
    public ?string $class = 'AvatarUploadForm';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

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
