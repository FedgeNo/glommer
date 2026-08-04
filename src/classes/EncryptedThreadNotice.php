<?php

declare(strict_types=1);

/**
 * The standing note at the top of a conversation where both people have
 * encryption keys - the counterpart to FederatedThreadNotice, for the same
 * reason: the difference is invisible otherwise, and it is a difference worth
 * knowing about in both directions.
 */
class EncryptedThreadNotice extends Notice
{
    public ?string $class = 'EncryptedThreadNotice';

    public function __construct()
    {
        parent::__construct(
            'Messages in this conversation are end-to-end encrypted: they are unlocked with your passphrase and read in your browsers, and this server stores only ciphertext it cannot open. Messages sent before encryption was turned on stay readable as they were.'
        );
    }
}
