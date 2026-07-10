<?php

declare(strict_types=1);

class ResetPasswordForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 ResetPasswordForm';
    public string $token;

    public function __construct(string $token)
    {
        parent::__construct();

        $this -> token = $token;
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/reset-password/?token=' . urlencode($this -> token));
        $this -> method = 'POST';

        $fields = new Fieldset('Choose a new password');
        $fields -> addContents(new InputField('newPassword', 'New password', 'password', 'At least 8 characters'));
        $fields -> addContents(new InputField('confirmPassword', 'Confirm new password', 'password', 'Confirm new password'));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Reset Password');

        return parent::toDOM();
    }
}
