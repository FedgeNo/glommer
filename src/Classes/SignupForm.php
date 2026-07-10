<?php

declare(strict_types=1);

class SignupForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 SignupForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/signup/');
        $this -> method = 'POST';

        $fields = new Fieldset('Create an account');
        $fields -> addContents(new InputField('username', 'Username', 'text', 'Lowercase letters, numbers, and _', 32));
        $fields -> addContents(new InputField('email', 'Email', 'email', 'you@example.com', 255));
        $fields -> addContents(new InputField('displayName', 'Display name (optional)', 'text', 'Display name (optional)', 100));
        $fields -> addContents(new InputField('password', 'Password', 'password', 'At least 8 characters'));
        $this -> contents[] = $fields;

        $this -> contents[] = new SubmitButton('Sign Up');

        return parent::toDOM();
    }
}
