<?php

declare(strict_types=1);

class ChangeEmailForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 ChangeEmailForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/change-email');
        $this -> method = 'POST';

        $fields = new Fieldset('Change your email address');

        $new_email = new InputField('newEmail', 'New email address', 'email', 'you@example.com', 255);
        $new_email -> autocomplete = 'email';
        $fields -> addContent($new_email);

        $fields -> addContent(new InputField('currentPassword', 'Current password', 'password', 'Current password'));

        $this -> contents[] = $fields;

        $this -> contents[] = new Notice('You\'ll need to verify the new address before you can keep using the site.');

        $this -> contents[] = new SubmitButton('Change Email');

        return parent::toDOM();
    }
}
