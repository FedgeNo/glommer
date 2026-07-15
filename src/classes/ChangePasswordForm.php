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

        $current_password = new InputField('currentPassword', 'Current password', 'password', 'Current password');
        $current_password -> labelVisible = true;
        $fields -> addContent($current_password);

        $new_password = new InputField('newPassword', 'New password', 'password', 'At least 8 characters');
        $new_password -> labelVisible = true;
        $fields -> addContent($new_password);

        $confirm_password = new InputField('confirmPassword', 'Confirm new password', 'password', 'Confirm new password');
        $confirm_password -> labelVisible = true;
        $fields -> addContent($confirm_password);

        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Change Password');

        return parent::toDOM();
    }
}
