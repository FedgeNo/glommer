<?php

declare(strict_types=1);

class LoginForm extends Form
{
    public ?string $class = 'LoginForm';
    public array $mixins = ['Card', 'd-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {

        $fields = new Fieldset('Log in');
        $fields -> addContent(new InputField('identifier', 'Username or email', 'text', 'Username or email', 255));
        $fields -> addContent(new InputField('password', 'Password', 'password', 'Password'));

        $remember_me = new CheckboxField('rememberMe', 'Remember me');
        $remember_me -> checked = true;
        $fields -> addContent($remember_me);

        $this -> contents[] = $fields;

        if (Turnstile::isEnabled()) {
            // Turnstile's iframe loads in asynchronously; without a reserved
            // box the submit button jumps down when it appears. The footer
            // reserves that height up front and puts the button on the
            // opposite side, so nothing shifts when it loads.
            $footer = new Div();
            $footer -> class = 'LoginFormFooter';
            // No align-items override: SubmitButton already carries
            // align-self-end (the same class that right-aligned it before),
            // which now bottom-aligns it against the reserved Turnstile box
            // instead - same corner it always stuck to, just beside the box.
            $footer -> mixins = ['d-flex', 'justify-content-between'];
            $footer -> addContent(new TurnstileWidget());
            $footer -> addContent(new SubmitButton('Log In'));
            $this -> contents[] = $footer;
        } else {
            $this -> contents[] = new SubmitButton('Log In');
        }

        return parent::toDOM();
    }
}
