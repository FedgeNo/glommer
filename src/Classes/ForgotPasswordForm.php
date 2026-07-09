<?php

declare(strict_types=1);

class ForgotPasswordForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 ForgotPasswordForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/forgot-password/');
        $this -> method = 'POST';

        $fields = new Fieldset('Reset your password');
        $fields -> addContents(new InputField('email', 'Email', 'email', 'you@example.com', 255));
        $this -> contents[] = $fields;

        $submit = new Button();
        $submit -> type = 'submit';
        $submit -> class = 'Btn align-self-start';
        $submit -> contents[] = 'Send Reset Link';
        $this -> contents[] = $submit;

        return parent::toDOM();
    }
}
