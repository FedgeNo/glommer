<?php

declare(strict_types=1);

/**
 * The Settings section for end-to-end encrypted messaging. Everything
 * cryptographic happens in the browser (Controllers.js's EncryptedMessagesSetting): the
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
        $words = Strings::for(self::class);

        $explanation = new Paragraph();
        $explanation -> contents[] = (string) ($words['explanation'] ?? '');
        $this -> addContent($explanation);

        $warning = new Paragraph();
        $warning -> contents[] = (string) ($words['noRecovery'] ?? '');
        $this -> addContent($warning);

        if (Auth::user() -> messagePublicKey === null) {
            $this -> addContent(new MessageKeySetupForm(false));
        } else {
            // Changing the passphrase happens entirely in the browser (unwrap
            // under the old, rewrap under the new), so the keys come down with
            // the section that does it. Ciphertext and a public key, both this
            // member's own - and on the element rather than in the config
            // cookie, which every later request would carry them back up in.
            $this -> attributes['data-public-key'] = (string) Auth::user() -> messagePublicKey;
            $this -> attributes['data-wrapped-private-key'] = (string) Auth::user() -> messageWrappedPrivateKey;

            $status = new Paragraph();
            $status -> contents[] = (string) ($words['enabledStatus'] ?? '');
            $this -> addContent($status);

            $this -> addContent(new MessageKeyPassphraseForm());
            $this -> addContent(new MessageKeySetupForm(true));
        }

        return parent::toDOM();
    }
}
