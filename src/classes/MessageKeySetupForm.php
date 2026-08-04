<?php

declare(strict_types=1);

/**
 * Creates (or, as the reset variant, replaces) the messaging keypair: pick a
 * passphrase, and EncryptedMessagesSetting.js does the rest in the browser.
 * The two variants are one form because they are one operation - the only
 * difference is what replacing an existing key costs, which the reset variant
 * says out loud.
 */
class MessageKeySetupForm extends FormForm
{
    public bool $reset;

    public function __construct(bool $reset)
    {
        parent::__construct();

        $this -> reset = $reset;
    }

    public function toDOM(): \DOMElement
    {
        if ($this -> reset) {
            $warning = new Paragraph();
            $warning -> contents[] = 'Forgotten your passphrase? Resetting creates new keys under a new one - but messages encrypted with the old keys can never be read again, by anyone.';
            $this -> contents[] = $warning;
        }

        $passphrase = new InputField('passphrase', $this -> reset ? 'New passphrase' : 'Passphrase', 'password');
        $passphrase -> autocomplete = 'new-password';
        $this -> contents[] = $passphrase;

        $confirm = new InputField('passphraseConfirm', 'Confirm passphrase', 'password');
        $confirm -> autocomplete = 'new-password';
        $this -> contents[] = $confirm;

        // Replacing keys decides who can read future messages, so the server
        // demands the account password - see api/message-keys.php.
        $account_password = new InputField('setupAccountPassword', 'Account password', 'password');
        $account_password -> autocomplete = 'current-password';
        $this -> contents[] = $account_password;

        $this -> contents[] = new SubmitButton($this -> reset ? 'Reset encryption keys' : 'Turn on encrypted messages');

        return parent::toDOM();
    }
}
