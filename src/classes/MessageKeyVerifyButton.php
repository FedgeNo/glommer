<?php

declare(strict_types=1);

/**
 * Records that the reader has compared this conversation's safety code with
 * the other person and found it to match. Kept in the browser rather than
 * here: what it guards against is this server handing out the wrong key, and a
 * note this server held could simply be rewritten to say whatever suited it.
 */
class MessageKeyVerifyButton extends ButtonButton
{
    public function toDOM(): \DOMElement
    {
        $this -> contents[] = (string) (Strings::for(self::class)['label'] ?? '');

        return parent::toDOM();
    }
}
