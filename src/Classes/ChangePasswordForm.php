<?php

declare(strict_types=1);

class ChangePasswordForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 ChangePasswordForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/api/change-password');
        $this -> method = 'POST';

        $fields = new Fieldset('Change your password');
        $fields -> addContent(new InputField('currentPassword', 'Current password', 'password', 'Current password'));
        $fields -> addContent(new InputField('newPassword', 'New password', 'password', 'At least 8 characters'));
        $fields -> addContent(new InputField('confirmPassword', 'Confirm new password', 'password', 'Confirm new password'));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Change Password');

        return parent::toDOM();
    }
}
