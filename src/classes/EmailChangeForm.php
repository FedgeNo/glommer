<?php

declare(strict_types=1);

class EmailChangeForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {

        $fields = new Fieldset('Change your email address');

        // The browser's saved address is the one being replaced, so offering it
        // back fills the field with the wrong answer.
        $new_email = new InputField('newEmail', 'New email address', 'email', 'New email address', 255);
        $new_email -> autocomplete = 'new-password';
        $new_email -> labelVisible = true;
        $fields -> addContent($new_email);

        $current_password = new InputField('currentPassword', 'Current password', 'password', 'Current password');
        $current_password -> labelVisible = true;
        $fields -> addContent($current_password);

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice('You\'ll need to verify the new address before you can keep using the site.');

        $this -> contents[] = new SubmitButton('Change Email');

        return parent::toDOM();
    }
}
