<?php

declare(strict_types=1);

/**
 * The confirmation step for reverting an email change: a button that POSTs the
 * revert token back to /revert-email, so the revert runs only on a deliberate
 * click, never on a bare GET of the link. The revert link is mailed to the
 * account's pre-change address, and email security scanners (SafeLinks,
 * Mimecast, Gmail prefetch) fetch every link they see - a GET-side revert
 * would let one of those blind fetches undo a legitimate change.
 */
class RevertEmailForm extends Form
{
    public ?string $class = 'Card d-flex flex-column gap-2 RevertEmailForm';
    public string $token;

    public function __construct(string $token)
    {
        parent::__construct();

        $this -> token = $token;
    }

    public function toDOM(): \DOMElement
    {
        $this -> action = ServerURL::absolute('/revert-email');
        $this -> method = 'POST';

        $token_input = new HiddenInput();
        $token_input -> name = 'token';
        $token_input -> value = $this -> token;
        $this -> contents[] = $token_input;

        $this -> contents[] = new SubmitButton('Revert email change');

        return parent::toDOM();
    }
}
