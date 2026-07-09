<?php

declare(strict_types=1);

class ChangePasswordForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 ChangePasswordForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/api/change-password');
        $this -> method = 'POST';

        $fields = new Fieldset('Change your password');
        $fields -> addContents(new InputField('currentPassword', 'Current password', 'password', 'Current password'));
        $fields -> addContents(new InputField('newPassword', 'New password', 'password', 'At least 8 characters'));
        $fields -> addContents(new InputField('confirmPassword', 'Confirm new password', 'password', 'Confirm new password'));
        $this -> contents[] = $fields;

        $submit = new Button();
        $submit -> type = 'submit';
        $submit -> class = 'Btn align-self-start';
        $submit -> contents[] = 'Change Password';
        $this -> contents[] = $submit;

        return parent::toDOM();
    }
}
