<?php

declare(strict_types=1);

class SignupForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 SignupForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/signup');
        $this -> method = 'POST';

        $fields = new Fieldset('Create an account');

        // Autocomplete hints mark this as a registration form: 'new-password'
        // tells the browser this isn't a login, so it stops autofilling saved
        // credentials over the placeholders (and offers to save the new ones).
        $username = new InputField('username', 'Username', 'text', 'Username: Lowercase letters, numbers, and _', User::MAX_USERNAME_LENGTH);
        $username -> autocomplete = 'username';
        $fields -> addContent($username);

        // Filled in by main.js as the name is typed. Empty (and so invisible)
        // until there's something to report, and announced politely so a
        // screen reader hears the verdict without it interrupting typing.
        $availability = new Paragraph();
        $availability -> class = 'UsernameAvailability text-sm';
        $availability -> attributes['aria-live'] = 'polite';
        $fields -> addContent($availability);

        $email = new InputField('email', 'Email', 'email', 'Valid email address', 255);
        $email -> autocomplete = 'email';
        $fields -> addContent($email);

        $display_name = new InputField('displayName', 'Display name (optional)', 'text', 'Display name (optional)', 50);
        $display_name -> autocomplete = 'nickname';
        $fields -> addContent($display_name);

        $bio = new TextareaField('description', 'Bio (optional)', 'A short bio - #hashtags, @mentions, and links become clickable', 500);
        $fields -> addContent($bio);

        $password = new InputField('password', 'Password', 'password', 'Password: At least 8 characters');
        $password -> autocomplete = 'new-password';
        $fields -> addContent($password);

        $remember_me = new CheckboxField('rememberMe', 'Remember me');
        $remember_me -> checked = true;
        $fields -> addContent($remember_me);

        $this -> contents[] = $fields;

        if (Turnstile::isEnabled()) {
            $this -> contents[] = new TurnstileWidget();
        }

        $this -> contents[] = new SubmitButton('Sign Up');

        return parent::toDOM();
    }
}
