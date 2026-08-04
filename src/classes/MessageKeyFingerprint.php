<?php

declare(strict_types=1);

/**
 * The safety code for an encrypted conversation: a short string standing for
 * the pair of keys it is encrypted with, which both people can read to each
 * other over the phone (or anywhere else that isn't this server) to check that
 * nothing is sitting in the middle.
 *
 * The code is computed in the browser, never here, and that is the whole point
 * - the server is what tells each side the other's key, so a code it calculated
 * would agree with whatever it had handed out. MessageKeyFingerprint.js fills
 * this in from the keys the browser is actually encrypting with, and remembers
 * a code the reader has confirmed so a later change is called out rather than
 * passing unnoticed.
 */
class MessageKeyFingerprint extends Div
{
    public ?string $class = 'MessageKeyFingerprint';

    public function toDOM(): \DOMElement
    {
        $explanation = new Paragraph();
        $explanation -> contents[] = 'Read this code to each other some other way - out loud, in person, over a call. If it matches on both sides, nobody is between you.';
        $this -> addContent($explanation);

        // Filled in by the browser; empty here because a code this server
        // worked out would prove nothing about the keys it handed over.
        $this -> addContent(new MessageKeyFingerprintCode());
        $this -> addContent(new MessageKeyVerifyButton());

        return parent::toDOM();
    }
}
