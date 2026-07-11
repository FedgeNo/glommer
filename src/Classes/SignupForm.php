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

        // Autocomplete hints mark this as a registration form: 'new-password'
        // tells the browser this isn't a login, so it stops autofilling saved
        // credentials over the placeholders (and offers to save the new ones).
        $username = new InputField('username', 'Username', 'text', 'Lowercase letters, numbers, and _', 32);
        $username -> autocomplete = 'username';
        $fields -> addContents($username);

        $email = new InputField('email', 'Email', 'email', 'you@example.com', 255);
        $email -> autocomplete = 'email';
        $fields -> addContents($email);

        $display_name = new InputField('displayName', 'Display name (optional)', 'text', 'Display name (optional)', 100);
        $display_name -> autocomplete = 'nickname';
        $fields -> addContents($display_name);

        $password = new InputField('password', 'Password', 'password', 'At least 8 characters');
        $password -> autocomplete = 'new-password';
        $fields -> addContents($password);

        $remember_me = new CheckboxField('rememberMe', 'Remember me');
        $remember_me -> checked = true;
        $fields -> addContents($remember_me);

        $this -> contents[] = $fields;

        if (Turnstile::isEnabled()) {
            $this -> contents[] = new TurnstileWidget();
        }

        $this -> contents[] = new SubmitButton('Sign Up');

        return parent::toDOM();
    }
}
