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
        'siteTitleLabel' => 'Site Title',
        'mailFromAddressLabel' => 'Mail From Address',
        // {host} is the hostname parsed from the site URL above, spliced into
        // the middle of the sentence rather than glued to one end of it.
        'serverNameConfirmedLabel' => 'I\'ve set "ServerName {host}" and "UseCanonicalName On" in my web server\'s config (only checked if the automated live test can\'t complete - see README.md\'s HTTPS section)',
        'databaseLegend' => 'Database',
        'databaseHostLabel' => 'Database Host',
        'databasePortLabel' => 'Database Port',
        'databaseNameLabel' => 'Database Name',
        'databaseAdminUsernameLabel' => 'Database Admin Username',
        'databaseAdminPasswordLabel' => 'Database Admin Password',
        'webSocketTLSLegend' => 'WebSocket TLS (Optional)',
        'certificatePathLabel' => 'Certificate Path',
        'certificatePathPlaceholder' => 'Leave blank to generate automatically via mkcert',
        'keyPathLabel' => 'Key Path',
        'keyPathPlaceholder' => 'Leave blank to generate automatically via mkcert',
        'botProtectionLegend' => 'Bot Protection (Optional)',
        'turnstileSiteKeyLabel' => 'Cloudflare Turnstile Site Key',
        'turnstileSiteKeyPlaceholder' => 'Leave blank to skip',
        'turnstileSecretKeyLabel' => 'Cloudflare Turnstile Secret Key',
        'turnstileSecretKeyPlaceholder' => 'Leave blank to skip',
        'submit' => 'Set Up',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => 'Current Passphrase',
        'newPassphraseLabel' => 'New Passphrase',
        'confirmNewPassphraseLabel' => 'Confirm New Passphrase',
        'accountPasswordLabel' => 'Account Password',
        'submit' => 'Change Passphrase',
    ],

    'PasswordResetForm' => [
        'legend' => 'Choose a New Password',
        'newPasswordLabel' => 'New Password',
        'newPasswordPlaceholder' => 'At least 8 characters',
        'confirmPasswordLabel' => 'Confirm New Password',
        'submit' => 'Reset Password',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'Reset Your Password',
        'emailLabel' => 'Email',
        'submit' => 'Send Reset Link',
    ],

    'EmailRevertForm' => [
        'submit' => 'Revert Email Change',
    ],

    'EmailVerifyForm' => [
        'submit' => 'Verify Email Address',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => 'Turn Them Back On',
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
        'label' => 'Log Out Everywhere',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Verify With Google to Delete',
    ],

    'GoogleSignInButton' => [
        'label' => 'Continue With Google',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'Edit Profile',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'Get notifications on this device even when the site isn\'t open. This is a per-browser choice - turn it on wherever you want to be reached.',
        // Server-rendered once, as the resting 'off' text a no-JS visitor is
        // left with; PushNotificationSetting.js reads both sides of this pair
        // to keep the button's label in step with the browser's own
        // subscription state after that - see that file.
        'label' => [
            'off' => 'Enable on This Device',
            'on' => 'Turn Off on This Device',
        ],
        'unsupported' => 'Push isn\'t supported in this browser',
    ],
];
