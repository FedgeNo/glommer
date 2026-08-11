<?php

declare(strict_types=1);

/**
 * The words the notification and direct-messaging classes not already covered
 * by MessagingAndStatus.php say, in English.
 *
 * A fragment of src/locales/en.php - see that file for what a fragment is and
 * why converting these classes doesn't have to touch the ones anybody else is
 * converting at the same time.
 */

return [
    'Notification' => [
        // The system types say the same thing regardless of who triggered
        // them, so they carry no {name}.
        'postReady' => 'Your media has finished processing and is now live',
        'scheduledPostLive' => 'Your scheduled post is now live',
        'uploadPartlyFailed' => 'Your post is live, but one or more of its files couldn\'t be processed',
        'uploadFailed' => 'One of your uploads failed to process and was not posted',
        'mailerFailed' => 'Email delivery failed - the mailer may be down. Please check your mail configuration.',
        // Names the page by the navigation link that reaches it and the section
        // by the legend MailSettingsForm renders, so both things the reader is
        // told to look for are things they will actually see.
        'mailFromNotConfigured' => 'No mail "from" address is configured, so emails can\'t be sent. Set one in Admin Settings (Outgoing Mail section) or via bin/install.php.',
        'systemError' => 'A server error occurred. Check the error log for details.',
        'passwordRemovedGoogle' => 'Your password was removed when you signed in with Google. Use "Forgot Password" if you want to set a new one.',
        // {name} rather than concatenation, so a language can put the actor
        // anywhere in the sentence, or leave them out entirely.
        'like' => '{name} liked your post',
        'repost' => '{name} reposted your post',
        'reply' => '{name} replied to your post',
        'friendRequest' => '{name} sent you a friend request',
        'friendAccepted' => '{name} accepted your friend request',
        'message' => '{name} sent you a message',
        'mention' => '{name} mentioned you in a post',
        'follow' => '{name} followed you from another server',
        // What an unrecognised type still says, matching the match()
        // default arm this replaced.
        'default' => '{name} did something',
    ],

    'NotificationList' => [
        'emptyNotice' => 'No notifications yet.',
    ],

    'NotificationsNavLink' => [
        'label' => 'Notifications',
        'unseen' => 'Unseen notifications',
    ],

    'NotificationTestPanel' => [
        'intro' => 'Send a test notification to yourself (the admin). It should appear instantly as a toast and in the notification dropdown.',
        'button' => 'Send Test Notification',
        // The other states NotificationTestPanel.js's button cycles through
        // after a click - kept here too so it never reverts to English
        // partway through, under another locale.
        'sending' => 'Sending…',
        'sent' => 'Sent!',
        'failed' => 'Failed',
    ],

    'MessageDot' => [
        'label' => 'Unread messages',
    ],

    'NavAlertDot' => [
        'label' => 'Something new in the menu',
    ],

    'Message' => [
        'encrypted' => 'Encrypted message',
        // Read only by Message.js, when the keys held in this browser don't
        // open an envelope. The server can't render this itself - all it
        // ever holds is ciphertext, so it has no way to know decryption
        // failed.
        'decryptionFailed' => 'This message was encrypted with keys that no longer exist.',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'Message',
        'bodyPlaceholder' => 'Write a message',
        'send' => 'Send',
    ],

    'MessageList' => [
        'emptyNotice' => 'No messages yet.',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'Read this code to each other some other way - out loud, in person, over a call. If it matches on both sides, nobody is between you.',
        // The next two are read only by MessageKeyFingerprint.js: the code
        // itself is computed in the browser (see the class docblock), so the
        // states that depend on it never reach a PHP render either.
        'changed' => 'This code has changed since you checked it. That happens when one of you resets your encryption keys - but it is also what someone reading this conversation would look like. Check the new code with them before trusting it.',
        'verified' => 'You have checked this code.',
    ],

    'MessageKeyVerifyButton' => [
        'label' => 'Mark as Verified',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'Passphrase',
        'passphrasePlaceholder' => 'Passphrase to unlock this conversation',
        'submit' => 'Unlock',
    ],

    'Conversation' => [
        // No 'link' piece: the middle is a <time> element RelativeTime
        // renders itself, not words this class has to say.
        'lastMessage' => ['before' => 'Last message ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => 'Sensitive media',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => 'Show Sensitive Media by Default',
    ],
];
