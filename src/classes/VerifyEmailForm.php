<?php

declare(strict_types=1);

/**
 * The confirmation step for verifying an email address: a button that POSTs the
 * verification token back to /verify-email, so the token is consumed only on a
 * deliberate click, never on a bare GET of the link. Email security scanners
 * (SafeLinks, Mimecast, Gmail prefetch) fetch every link they see - a GET-side
 * verify would let one of those blind fetches consume the token before the real
 * user ever opened the message. Mirrors RevertEmailForm.
 */
class VerifyEmailForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 VerifyEmailForm';
    public string $token;

    public function __construct(string $token)
    {
        parent::__construct();

        $this -> token = $token;
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/verify-email');
        $this -> method = 'POST';

        $token_input = new HiddenInput();
        $token_input -> name = 'token';
        $token_input -> value = $this -> token;
        $this -> contents[] = $token_input;

        $this -> contents[] = new SubmitButton('Verify email address');

        return parent::toDOM();
    }
}
