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
    public function toDOM(): \DOMElement
    {
        $passphrase = new InputField('messagePassphrase', 'Passphrase', 'password', 'Passphrase to unlock this conversation');
        $passphrase -> autocomplete = 'current-password';
        $this -> contents[] = $passphrase;

        $this -> contents[] = new SubmitButton('Unlock');

        return parent::toDOM();
    }
}
