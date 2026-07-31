<?php

declare(strict_types=1);

class PasswordResetRequestForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {

        $fields = new Fieldset('Reset your password');
        $fields -> addContent(new InputField('email', 'Email', 'email', 'Email', 255));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Send Reset Link');

        return parent::toDOM();
    }
}
