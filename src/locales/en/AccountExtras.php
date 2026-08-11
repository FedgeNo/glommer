<?php

declare(strict_types=1);

/**
 * The words the smaller account-related classes say, in English - first-run
 * setup, the messaging passphrase change, password reset, the email
 * revert/verify confirmation steps, the digest resubscribe link, remembered
 * devices, signing out everywhere, Google account linking, the profile edit
 * affordance, and browser push. See src/locales/en.php for what a fragment is
 * and why converting these classes doesn't have to touch the ones anybody
 * else is converting at the same time.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'Site',
        'siteURLLabel' => 'Site URL',
        'siteTitleLabel' => 'Site title',
        'mailFromAddressLabel' => 'Mail from address',
        // {host} is the hostname parsed from the site URL above, spliced into
        // the middle of the sentence rather than glued to one end of it.
        'serverNameConfirmedLabel' => 'I\'ve set "ServerName {host}" and "UseCanonicalName On" in my web server\'s config (only checked if the automated live test can\'t complete - see README.md\'s HTTPS section)',
        'databaseLegend' => 'Database',
        'databaseHostLabel' => 'Database host',
        'databasePortLabel' => 'Database port',
        'databaseNameLabel' => 'Database name',
        'databaseAdminUsernameLabel' => 'Database admin username',
        'databaseAdminPasswordLabel' => 'Database admin password',
        'webSocketTLSLegend' => 'WebSocket TLS (optional)',
        'certificatePathLabel' => 'Certificate path',
        'certificatePathPlaceholder' => 'Leave blank to generate automatically via mkcert',
        'keyPathLabel' => 'Key path',
        'keyPathPlaceholder' => 'Leave blank to generate automatically via mkcert',
        'botProtectionLegend' => 'Bot protection (optional)',
        'turnstileSiteKeyLabel' => 'Cloudflare Turnstile site key',
        'turnstileSiteKeyPlaceholder' => 'Leave blank to skip',
        'turnstileSecretKeyLabel' => 'Cloudflare Turnstile secret key',
        'turnstileSecretKeyPlaceholder' => 'Leave blank to skip',
        'submit' => 'Set Up',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Current passphrase',
        'newPassphraseLabel' => 'New passphrase',
        'confirmNewPassphraseLabel' => 'Confirm new passphrase',
        'accountPasswordLabel' => 'Account password',
        'submit' => 'Change passphrase',
    ],

    'PasswordResetForm' => [
        'legend' => 'Choose a new password',
        'newPasswordLabel' => 'New password',
        'newPasswordPlaceholder' => 'At least 8 characters',
        'confirmPasswordLabel' => 'Confirm new password',
        'submit' => 'Reset Password',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Reset your password',
        'emailLabel' => 'Email',
        'submit' => 'Send Reset Link',
    ],

    'EmailRevertForm' => [
        'submit' => 'Revert email change',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Verify email address',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => 'Turn them back on',
    ],

    'EmailDigestSetting' => [
        'label' => 'Email me what I missed when I have been away a while',
    ],

    'RememberedDevice' => [
        'unknownDevice' => 'Unknown device',
        // {browser}/{os} rather than concatenation: the joining word is
        // English's "on", which not every language puts between two nouns
        // the same way. The names themselves are brands, not prose - the
        // same in every locale - so the code supplies them; see
        // RememberedDevice::describe().
        'browserOnOS' => '{browser} on {os}',
        'thisDevice' => ' (this device)',
        'lastUsed' => ['before' => 'Last used ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'End every active session and forget every remembered device. You will be signed out of all browsers, including this one.',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'Log out everywhere',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Verify with Google to delete',
    ],

    'GoogleSignInButton' => [
        'label' => 'Continue with Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Edit profile',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Get notifications on this device even when the site isn\'t open. This is a per-browser choice - turn it on wherever you want to be reached.',
        // Server-rendered once, as the resting 'off' text a no-JS visitor is
        // left with; PushNotificationSetting.js reads both sides of this pair
        // to keep the button's label in step with the browser's own
        // subscription state after that - see that file.
        'label' => [
            'off' => 'Enable on this device',
            'on' => 'Turn off on this device',
        ],
        'unsupported' => 'Push isn\'t supported in this browser',
    ],
];
