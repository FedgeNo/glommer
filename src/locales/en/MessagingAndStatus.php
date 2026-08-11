<?php

declare(strict_types=1);

/**
 * The words the messaging and admin-status classes say, in English.
 *
 * A fragment of src/locales/en.php - see that file for what a fragment is and
 * why converting these classes doesn't have to touch the ones anybody else is
 * converting at the same time.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'Forgotten your passphrase? Resetting creates new keys under a new one - but messages encrypted with the old keys can never be read again, by anyone.',
        'requirements' => 'At least 12 characters, and not your account password - that one is sent to this server, and your passphrase must never be.',
        'passphraseLabel' => 'Passphrase',
        'resetPassphraseLabel' => 'New Passphrase',
        'confirmLabel' => 'Confirm Passphrase',
        'accountPasswordLabel' => 'Account Password',
        'submitLabel' => 'Turn On Encrypted Messages',
        'resetSubmitLabel' => 'Reset Encryption Keys',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'End-to-end encrypted messages are locked and unlocked in your browser: this server relays and stores them without being able to read them. Your key is protected by a passphrase, and the same passphrase unlocks your messages from any browser. Conversations are encrypted once both people have turned this on; messages to people on other servers stay unencrypted, because federation has no way to carry them otherwise.',
        'noRecovery' => 'There is no way to recover a lost passphrase - not even for the administrator. Losing it means losing your encrypted messages.',
        'enabledStatus' => 'Encrypted messages are on.',
    ],

    'MessagePrivacyButton' => [
        // First entry is the fallback for a state nobody has written a
        // phrasing for (see MessagePrivacyButton::toDOM()), so the most
        // complete state leads rather than whichever sorts first.
        'encrypted' => [
            'label' => '🔒 Encrypted',
            'explanation' => 'Messages in this conversation are end-to-end encrypted: they are unlocked with your passphrase and read in your browsers, and what this server stores is ciphertext. Check the safety code at the bottom of the thread with the other person to be sure nobody is between you. Messages sent before encryption was turned on stay readable as they were.',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 Not encrypted',
            // {handle} rather than concatenation, so a language can put it
            // anywhere in the sentence, or leave it out.
            'explanation' => 'Messages here will be end-to-end encrypted once {handle} turns on encrypted messages in their settings.',
        ],
        'awaiting-yours' => [
            'label' => '🔓 Not encrypted',
            'explanation' => 'Messages here are not end-to-end encrypted. Turn on encrypted messages in Settings to secure this conversation.',
        ],
        'federated' => [
            'label' => '🔓 Not encrypted',
            'explanation' => '{handle} is on another server. Messages in this conversation are stored on that server as well as this one, and its administrator can read them - the protocol between servers has no way to encrypt them. Keep anything sensitive to conversations on this site.',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'Follow Fediverse Accounts',
        'notice' => 'Paste one or more handles, e.g. @user@example.social - any separator between them works.',
        'handlesLabel' => 'Fediverse Handles to Follow',
        'submit' => 'Follow',
        'statusPending' => 'pending',
        'statusAccepted' => 'accepted',
    ],

    'ServerBlockForm' => [
        'legend' => 'Block a Server',
        'description' => 'Refuses everything from that server and everything under it: no deliveries in, none out, and existing follows in both directions are dropped.',
        'serverLabel' => 'Server',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => 'Reason',
        'reasonPlaceholder' => 'Why this server is blocked',
        'submit' => 'Block Server',
    ],

    'VideoCallTestPanel' => [
        'intro' => 'Runs the parts of call setup that can be checked from one browser. Everything up to an actual peer-to-peer connection is testable here; connecting to another person needs that person.',
    ],

    'VideoCallTestButton' => [
        'label' => 'Run the Check',
    ],

    'WebSocketStatus' => [
        'ok' => 'WebSocket server: Running',
        // {detail} carries EnvironmentChecker's own diagnostic - which socket,
        // which port, which errno - which it says in the reader's language
        // from its own entry, so it rides in here as data.
        'failed' => 'WebSocket server: {detail}',
        // What the server renders into the line before the browser has tried
        // anything. The four below replace it once there is a real state to
        // report - scripts/WebSocketManager.js reads them from this same entry,
        // since it is writing into the element this class rendered.
        'clientTesting' => 'Browser connection: Testing…',
        'clientConnecting' => 'Browser connection: Connecting…',
        'clientConnected' => 'Browser connection: Connected',
        'clientDisconnecting' => 'Browser connection: Disconnecting…',
        'clientNotConnected' => 'Browser connection: Not connected',
    ],

    'UploadWorkerStatus' => [
        'running' => 'Worker service: Running',
        'stopped' => 'Worker service: Not running - staged uploads will never be transcoded until it is restarted',
        'unknown' => 'Worker service: Unknown - either systemctl isn\'t available on this host, or SELinux is denying the web server\'s own status query (run bin/install.php as root to fix that)',
        'queue' => 'Queue: {staging} staging, {pending} pending, {processing} processing',
    ],

    'TrendingTimerStatus' => [
        'running' => 'Trending timer: Running',
        'stopped' => 'Trending timer: Not running - trending topics will only refresh via the read-path self-heal (Trending::current()), not on a schedule. Run bin/install.php as root to set it up.',
        'unknown' => 'Trending timer: Unknown - either systemctl isn\'t available on this host, or SELinux is denying the web server\'s own status query (run bin/install.php as root to fix that)',
    ],
];
