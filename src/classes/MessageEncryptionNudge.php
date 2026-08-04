<?php

declare(strict_types=1);

/**
 * The note at the top of a plaintext conversation between two members here -
 * encryption takes both people's keys, so it names whichever side is missing.
 * Deliberately honest the same way FederatedThreadNotice is: a thread that
 * could be encrypted and isn't should say so where the messages are written.
 */
class MessageEncryptionNudge extends Notice
{
    public ?string $class = 'MessageEncryptionNudge';

    public function __construct(bool $viewer_has_keys, string $handle)
    {
        parent::__construct($viewer_has_keys
            ? 'Messages here will be end-to-end encrypted once ' . $handle . ' turns on encrypted messages in their settings.'
            : 'Messages here are not end-to-end encrypted. Turn on encrypted messages in Settings to secure this conversation.');
    }
}
