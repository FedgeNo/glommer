<?php

declare(strict_types=1);

class ResetPasswordForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];
    public string $token;

    public function __construct(string $token)
    {
        parent::__construct();

        $this -> token = $token;
    }

    public function toDOM(): \DOMElement
    {

        $token_input = new HiddenInput();
        $token_input -> name = 'token';
        $token_input -> value = $this -> token;
        $this -> contents[] = $token_input;

        $fields = new Fieldset('Choose a new password');
        $fields -> addContent(new InputField('newPassword', 'New password', 'password', 'At least 8 characters'));
        $fields -> addContent(new InputField('confirmPassword', 'Confirm new password', 'password', 'Confirm new password'));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Reset Password');

        return parent::toDOM();
    }
}
