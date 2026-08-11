<?php

declare(strict_types=1);

/**
 * The words the account/auth forms say, in English - signing in, signing up,
 * and the settings forms for password, email, two-factor, server migration,
 * and account deletion. See src/locales/en.php for what a fragment is and why
 * converting these classes doesn't have to touch the ones anybody else is
 * converting at the same time.
 */

return [
    'LoginForm' => [
        'legend' => 'Log In',
        'identifier' => 'Username or Email',
        'password' => 'Password',
        'rememberMe' => 'Remember Me',
        'submit' => 'Log In',
    ],

    'SignupForm' => [
        'legend' => 'Create an Account',
        'usernameLabel' => 'Username',
        'usernamePlaceholder' => 'Lowercase letters, numbers, and _',
        'emailLabel' => 'Email',
        'emailPlaceholder' => 'Valid email address',
        'displayName' => 'Display Name (Optional)',
        'bioLabel' => 'Bio (Optional)',
        'bioPlaceholder' => 'A short bio - #hashtags, @mentions, and links become clickable',
        'passwordLabel' => 'Password',
        'passwordPlaceholder' => 'Password: At least 8 characters',
        'rememberMe' => 'Remember Me',
        'submit' => 'Sign Up',
    ],

    'PasswordChangeForm' => [
        'legend' => 'Change Your Password',
        'currentPassword' => 'Current Password',
        'newPasswordLabel' => 'New Password',
        'newPasswordPlaceholder' => 'At least 8 characters',
        'confirmPassword' => 'Confirm New Password',
        'submit' => 'Change Password',
    ],

    'EmailChangeForm' => [
        'legend' => 'Change Your Email Address',
        'newEmail' => 'New Email Address',
        'currentPassword' => 'Current Password',
        'notice' => 'You\'ll need to verify the new address before you can keep using the site.',
        'submit' => 'Change Email',
    ],

    'AccountDeleteForm' => [
        'legend' => 'Delete Your Account',
        'warning' => 'This permanently deletes your account, posts, and messages. This can\'t be undone.',
        'currentPassword' => 'Current Password',
        'submit' => 'Delete Account',
    ],

    'AccountMigrationForm' => [
        'legend' => 'Move to Another Server',
        // {destination}/{address} rather than concatenation, so a translation
        // is not stuck with the address landing wherever English puts it -
        // see PollOptionVotes for the same idea with a count.
        'movedNotice' => 'This account has moved to {destination}. Your followers were asked to follow you there.',
        'explanation' => 'Your followers are asked to follow you at the new account. Your posts stay here - object addresses belong to the server that made them, so they cannot be taken along.',
        'addressNotice' => 'The account you are moving to has to list this one under "also known as" first. Your address here is {address}.',
        'movedToLabel' => 'Move To',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => 'Also Known As',
        'aliasesExplanation' => 'Accounts elsewhere that are also you. Listing one here lets that account move to this one - it is the permission, not the move itself. One address per line.',
        'aliasesLabel' => 'Your Other Accounts',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => 'Save',
    ],

    'TwoFactorForm' => [
        'legend' => 'Enter Your Verification Code',
        'explanation' => 'We emailed you a verification code. Enter it below to finish logging in.',
        'code' => 'Verification Code',
        'submit' => 'Verify',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => 'Two-Factor Authentication Is On', 'off' => 'Two-Factor Authentication Is Off'],
        'explanation' => [
            'on' => 'When you log in, we\'ll email a verification code you have to enter to finish signing in.',
            'off' => 'Add a second step at login: we\'ll email a verification code you have to enter, so your password alone isn\'t enough to get in.',
        ],
        'currentPassword' => 'Current Password',
        'submit' => ['on' => 'Turn Off Two-Factor Authentication', 'off' => 'Turn On Two-Factor Authentication'],
    ],

    'VerificationNotice' => [
        'instructions' => 'Please check your inbox and click the verification link we sent to confirm your email address. If you don\'t see it, check your junk/spam folder.',
    ],

    'VerificationResendButton' => [
        'label' => 'Resend Verification Email',
    ],
];
