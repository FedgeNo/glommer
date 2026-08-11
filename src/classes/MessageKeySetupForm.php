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
        $words = Strings::for(self::class);

        if ($this -> reset) {
            $warning = new Paragraph();
            $warning -> contents[] = (string) ($words['resetWarning'] ?? '');
            $this -> contents[] = $warning;
        }

        $requirements = new Paragraph();
        $requirements -> contents[] = (string) ($words['requirements'] ?? '');
        $this -> contents[] = $requirements;

        $passphrase = new InputField('passphrase', (string) ($this -> reset ? ($words['resetPassphraseLabel'] ?? '') : ($words['passphraseLabel'] ?? '')), 'password');
        $passphrase -> autocomplete = 'new-password';
        $this -> contents[] = $passphrase;

        $confirm = new InputField('passphraseConfirm', (string) ($words['confirmLabel'] ?? ''), 'password');
        $confirm -> autocomplete = 'new-password';
        $this -> contents[] = $confirm;

        // Replacing keys decides who can read future messages, so the server
        // demands the account password - see api/message-keys.php.
        $account_password = new InputField('setupAccountPassword', (string) ($words['accountPasswordLabel'] ?? ''), 'password');
        $account_password -> autocomplete = 'current-password';
        $this -> contents[] = $account_password;

        $this -> contents[] = new SubmitButton((string) ($this -> reset ? ($words['resetSubmitLabel'] ?? '') : ($words['submitLabel'] ?? '')));

        return parent::toDOM();
    }
}
