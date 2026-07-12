<?php

declare(strict_types=1);

class LoginForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 LoginForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/login');
        $this -> method = 'POST';

        $fields = new Fieldset('Log in');
        $fields -> addContents(new InputField('identifier', 'Username or email', 'text', 'Username or email', 255));
        $fields -> addContents(new InputField('password', 'Password', 'password', 'Password'));

        $remember_me = new CheckboxField('rememberMe', 'Remember me');
        $remember_me -> checked = true;
        $fields -> addContents($remember_me);

        $this -> contents[] = $fields;

        if (Turnstile::isEnabled()) {
            $this -> contents[] = new TurnstileWidget();
        }

        $this -> contents[] = new SubmitButton('Log In');

        return parent::toDOM();
    }
}
