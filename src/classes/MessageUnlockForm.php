<?php

declare(strict_types=1);

/**
 * The passphrase prompt at the top of an encrypted conversation. Submitting it
 * never leaves the page: MessageUnlockForm.js unwraps the private key in the
 * browser, remembers it for the tab, and opens every envelope in the thread.
 * Hidden by that script the moment the tab already holds the unlocked key.
 */
class MessageUnlockForm extends FormForm
{
    public string $otherPublicKey;
    public string $ownPublicKey;
    public string $wrappedPrivateKey;

    public function __construct(string $other_public_key, string $own_public_key, string $wrapped_private_key)
    {
        parent::__construct();

        $this -> otherPublicKey = $other_public_key;
        $this -> ownPublicKey = $own_public_key;
        $this -> wrappedPrivateKey = $wrapped_private_key;
    }

    public function toDOM(): \DOMElement
    {
        // Both are ciphertext or a public key - nothing here is a secret the
        // viewer doesn't already hold - and they belong to this conversation
        // rather than to the session, so they travel on the form that uses
        // them.
        $this -> attributes['data-other-public-key'] = $this -> otherPublicKey;
        // Both public keys, because the conversation's safety code stands for
        // the pair (see MessageKeyFingerprint).
        $this -> attributes['data-own-public-key'] = $this -> ownPublicKey;
        $this -> attributes['data-wrapped-private-key'] = $this -> wrappedPrivateKey;

        $passphrase = new InputField('messagePassphrase', 'Passphrase', 'password', 'Passphrase to unlock this conversation');
        $passphrase -> autocomplete = 'current-password';
        $this -> contents[] = $passphrase;

        $this -> contents[] = new SubmitButton('Unlock');

        return parent::toDOM();
    }
}
