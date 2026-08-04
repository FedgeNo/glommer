<?php

declare(strict_types=1);

/**
 * The Settings section for end-to-end encrypted messaging. Everything
 * cryptographic happens in the browser (EncryptedMessagesSetting.js): the
 * keypair is generated there, the private key is wrapped under a passphrase
 * there, and only the public key and the wrapped blob are ever sent up - which
 * is why the same passphrase unlocks the messages from any browser, and why
 * there is nothing this server could do for someone who loses it.
 */
class EncryptedMessagesSetting extends Div
{
    public ?string $class = 'EncryptedMessagesSetting';

    public function toDOM(): \DOMElement
    {
        $explanation = new Paragraph();
        $explanation -> contents[] = 'End-to-end encrypted messages are locked and unlocked in your browser: this server relays and stores them without being able to read them. Your key is protected by a passphrase, and the same passphrase unlocks your messages from any browser. Conversations are encrypted once both people have turned this on; messages to people on other servers stay unencrypted, because federation has no way to carry them otherwise.';
        $this -> addContent($explanation);

        $warning = new Paragraph();
        $warning -> contents[] = 'There is no way to recover a lost passphrase - not even for the administrator. Losing it means losing your encrypted messages.';
        $this -> addContent($warning);

        if (Auth::user() -> messagePublicKey === null) {
            $this -> addContent(new MessageKeySetupForm(false));
        } else {
            $status = new Paragraph();
            $status -> contents[] = 'Encrypted messages are on.';
            $this -> addContent($status);

            $this -> addContent(new MessageKeyPassphraseForm());
            $this -> addContent(new MessageKeySetupForm(true));
        }

        return parent::toDOM();
    }
}
