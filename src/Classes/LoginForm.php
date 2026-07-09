<?php

declare(strict_types=1);

class LoginForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 LoginForm';

    public function toDOM(): \DOMElement
    {
        $this -> action = URL::absolute('/login/');
        $this -> method = 'POST';

        $fields = new Fieldset('Log in');
        $fields -> addContents(new InputField('identifier', 'Username or email', 'text', 'Username or email', 255));
        $fields -> addContents(new InputField('password', 'Password', 'password', 'Password'));
        $this -> contents[] = $fields;

        $submit = new Button();
        $submit -> type = 'submit';
        $submit -> class = 'Btn align-self-start';
        $submit -> contents[] = 'Log In';
        $this -> contents[] = $submit;

        return parent::toDOM();
    }
}
