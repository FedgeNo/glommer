<?php

declare(strict_types=1);

class ForgotPasswordForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 ForgotPasswordForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/forgot-password');
        $this -> method = 'POST';

        $fields = new Fieldset('Reset your password');
        $fields -> addContent(new InputField('email', 'Email', 'email', 'Email', 255));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Send Reset Link');

        return parent::toDOM();
    }
}
