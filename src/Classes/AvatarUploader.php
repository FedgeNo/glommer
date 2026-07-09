<?php

declare(strict_types=1);

class AvatarUploader extends Form
{
    public ?string $class = 'd-flex flex-column align-items-end gap-2 ms-auto AvatarUploader';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/api/upload-avatar');
        $this -> method = 'POST';
        $this -> enctype = 'multipart/form-data';

        $file_input = new FileInput();
        $file_input -> name = 'avatar';
        $file_input -> attributes['accept'] = 'image/*';
        $this -> contents[] = $file_input;

        $submit_button = new Button();
        $submit_button -> type = 'submit';
        $submit_button -> class = 'Btn';
        $submit_button -> contents[] = 'Update profile picture';
        $this -> contents[] = $submit_button;

        return parent::toDOM();
    }
}
