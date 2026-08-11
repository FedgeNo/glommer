<?php

declare(strict_types=1);

/**
 * The way back, on the page an unsubscribe link lands on: one button that
 * POSTs the same token to /unsubscribe and turns the digests back on.
 *
 * It exists because the link before it acts on a bare GET. Somebody who never
 * asked to unsubscribe - a mail scanner followed the link for them, or they
 * clicked it meaning to read what it said - can undo it where they stand,
 * still without signing in.
 */
class EmailDigestResubscribeForm extends FormForm
{
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];
    public string $token;

    public function __construct(string $token)
    {
        parent::__construct();

        $this -> token = $token;
    }

    public function toDOM(): \DOMElement
    {
        $token_input = new HiddenInput();
        $token_input -> name = 'token';
        $token_input -> value = $this -> token;
        $this -> contents[] = $token_input;

        $this -> contents[] = new SubmitButton((string) (Strings::for(self::class)['submit'] ?? ''));

        return parent::toDOM();
    }
}
