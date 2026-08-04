<?php

declare(strict_types=1);

/**
 * Changes the passphrase the messaging private key is wrapped under. The key
 * itself doesn't change - EncryptedMessagesSetting.js unwraps it with the
 * current passphrase and rewraps it with the new one, all in the browser -
 * so every encrypted message stays readable.
 */
class MessageKeyPassphraseForm extends FormForm
{
    public function toDOM(): \DOMElement
    {
        $current = new InputField('currentPassphrase', 'Current passphrase', 'password');
        $current -> autocomplete = 'current-password';
        $this -> contents[] = $current;

        $new = new InputField('newPassphrase', 'New passphrase', 'password');
        $new -> autocomplete = 'new-password';
        $this -> contents[] = $new;

        $confirm = new InputField('newPassphraseConfirm', 'Confirm new passphrase', 'password');
        $confirm -> autocomplete = 'new-password';
        $this -> contents[] = $confirm;

        $this -> contents[] = new SubmitButton('Change passphrase');

        return parent::toDOM();
    }
}
