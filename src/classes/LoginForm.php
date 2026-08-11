<?php

declare(strict_types=1);

class LoginForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $identifier = (string) ($words['identifier'] ?? '');
        $password = (string) ($words['password'] ?? '');
        $submit = (string) ($words['submit'] ?? '');

        $fields = new Fieldset((string) ($words['legend'] ?? ''));
        $fields -> addContent(new InputField('identifier', $identifier, 'text', $identifier, 255));
        $fields -> addContent(new InputField('password', $password, 'password', $password));

        $remember_me = new CheckboxField('rememberMe', (string) ($words['rememberMe'] ?? ''));
        $remember_me -> checked = true;
        $fields -> addContent($remember_me);

        $this -> contents[] = $fields;

        if (Turnstile::isEnabled()) {
            // Turnstile's iframe loads in asynchronously; without a reserved
            // box the submit button jumps down when it appears. The footer
            // reserves that height up front and puts the button on the
            // opposite side, so nothing shifts when it loads.
            $footer = new LoginFormFooter();
            // No align-items override: .SubmitButton aligns itself to the
            // trailing edge, which in this row bottom-aligns it against the
            // reserved Turnstile box - the same corner it always stuck to,
            // just beside the box rather than under it.
            $footer -> mixins = ['d-flex', 'justify-content-between'];
            $footer -> addContent(new TurnstileWidget());
            $footer -> addContent(new SubmitButton($submit));
            $this -> contents[] = $footer;
        } else {
            $this -> contents[] = new SubmitButton($submit);
        }

        return parent::toDOM();
    }
}
