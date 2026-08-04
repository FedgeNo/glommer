<?php

declare(strict_types=1);

class AvatarUploadForm extends Form
{
    public ?string $class = 'AvatarUploadForm';
    public array $mixins = ['d-flex', 'flex-column', 'align-items-end', 'gap-2', 'ms-auto'];

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
