<?php

declare(strict_types=1);

/**
 * Changes the passphrase the messaging private key is wrapped under. The key
 * itself doesn't change - Controllers.js's EncryptedMessagesSetting unwraps it with the
 * current passphrase and rewraps it with the new one, all in the browser -
 * so every encrypted message stays readable.
 */
class MessageKeyPassphraseForm extends FormForm
{
    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $current = new InputField('currentPassphrase', (string) ($words['currentPassphraseLabel'] ?? ''), 'password');
        $current -> autocomplete = 'current-password';
        $this -> contents[] = $current;

        $new = new InputField('newPassphrase', (string) ($words['newPassphraseLabel'] ?? ''), 'password');
        $new -> autocomplete = 'new-password';
        $this -> contents[] = $new;

        $confirm = new InputField('newPassphraseConfirm', (string) ($words['confirmNewPassphraseLabel'] ?? ''), 'password');
        $confirm -> autocomplete = 'new-password';
        $this -> contents[] = $confirm;

        // Rewrapping stores a new key blob, so the server demands the account
        // password - see api/message-keys.php.
        $account_password = new InputField('rewrapAccountPassword', (string) ($words['accountPasswordLabel'] ?? ''), 'password');
        $account_password -> autocomplete = 'current-password';
        $this -> contents[] = $account_password;

        $this -> contents[] = new SubmitButton((string) ($words['submit'] ?? ''));

        return parent::toDOM();
    }
}
