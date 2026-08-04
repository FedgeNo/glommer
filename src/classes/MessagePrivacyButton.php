<?php

declare(strict_types=1);

/**
 * The small privacy chip at the top right of the message composer: a couple of
 * words and an emoji saying what this conversation is, with the full
 * explanation a click away (MessageComposer.js pops it up).
 *
 * The states are the honest ones a thread can be in: end-to-end encrypted,
 * plaintext because one side hasn't set up keys yet (named, so it's clear
 * whose move it is), or federated - stored on a second server with no way to
 * encrypt it, which no amount of setup can change.
 */
class MessagePrivacyButton extends ButtonButton
{
    public function __construct(string $state, string $handle)
    {
        parent::__construct();

        [$label, $explanation] = match ($state) {
            'encrypted' => [
                '🔒 Encrypted',
                'Messages in this conversation are end-to-end encrypted: they are unlocked with your passphrase and read in your browsers, and what this server stores is ciphertext. Check the safety code at the bottom of the thread with the other person to be sure nobody is between you. Messages sent before encryption was turned on stay readable as they were.',
            ],
            'awaiting-theirs' => [
                '🔓 Not encrypted',
                'Messages here will be end-to-end encrypted once ' . $handle . ' turns on encrypted messages in their settings.',
            ],
            'awaiting-yours' => [
                '🔓 Not encrypted',
                'Messages here are not end-to-end encrypted. Turn on encrypted messages in Settings to secure this conversation.',
            ],
            'federated' => [
                '🔓 Not encrypted',
                $handle . ' is on another server. Messages in this conversation are stored on that server as well as this one, and its administrator can read them - the protocol between servers has no way to encrypt them. Keep anything sensitive to conversations on this site.',
            ],
        };

        $this -> attributes['data-privacy-explanation'] = $explanation;
        $this -> contents[] = $label;
    }
}
