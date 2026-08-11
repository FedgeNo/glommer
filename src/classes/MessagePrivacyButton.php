<?php

declare(strict_types=1);

/**
 * The small privacy chip at the top right of the message composer: a couple of
 * words and an emoji saying what this conversation is, with the full
 * explanation a click away (MessageComposer.js pops it up).
 *
 * The states are the honest ones a thread can be in: end-to-end encrypted,
 * plaintext because one side hasn't set up keys yet (named, so it's clear
 * whose move it is), or federated - stored on a second server with no way to
 * encrypt it, which no amount of setup can change.
 */
class MessagePrivacyButton extends ButtonButton
{
    public function __construct(private readonly string $state, private readonly string $handle)
    {
        parent::__construct();
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        // An unrecognised state still renders something rather than fataling
        // the composer - the same fallback LoginPrompt uses.
        $entry = $words[$this -> state] ?? reset($words);

        $this -> attributes['data-privacy-explanation'] = str_replace(
            '{handle}',
            $this -> handle,
            (string) ($entry['explanation'] ?? '')
        );
        $this -> contents[] = (string) ($entry['label'] ?? '');

        return parent::toDOM();
    }
}
