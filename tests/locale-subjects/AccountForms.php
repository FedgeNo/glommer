<?php

declare(strict_types=1);

/**
 * Converted account/auth form classes - see tests/NoEnglishInClassesTest.php
 * for what this list is for.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    LoginForm::class => static fn (): HTMLObject => new LoginForm(),
    SignupForm::class => static fn (): HTMLObject => new SignupForm(),
    PasswordChangeForm::class => static fn (): HTMLObject => new PasswordChangeForm(),
    EmailChangeForm::class => static fn (): HTMLObject => new EmailChangeForm(),
    AccountDeleteForm::class => static fn (): HTMLObject => new AccountDeleteForm(),

    // AccountMigrationForm reads the signed-in member off Auth::user() by
    // default, which needs a session this plain suite has no reason to start -
    // a fabricated User standing in for it is the same technique
    // ActivityPubActorTest uses for the same reason.
    AccountMigrationForm::class => static function (): HTMLObject {
        $user = new User();
        $user -> slug = 'someone';
        $user -> movedToURI = null;
        $user -> alsoKnownAs = null;

        return new AccountMigrationForm($user);
    },

    TwoFactorForm::class => static fn (): HTMLObject => new TwoFactorForm(),
    TwoFactorSettingsForm::class => static fn (): HTMLObject => new TwoFactorSettingsForm(false),
    VerificationNotice::class => static fn (): HTMLObject => new VerificationNotice(),
    VerificationResendButton::class => static fn (): HTMLObject => new VerificationResendButton(),
];
