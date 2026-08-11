<?php

declare(strict_types=1);

/**
 * How to build one of each converted account-extras class - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * Every class here renders without a database or a session (EmailDigestSetting
 * reads Auth::user() only through the nullsafe operator, unlike
 * EncryptedMessagesSetting's bare arrow), so all of them are constructible from
 * this plain suite.
 *
 * ProfileEditButton is converted but not listed here, the same way
 * PostRepostButton and PostBookmarkButton aren't: its only word is an
 * aria-label, and this test's render() reads textContent, which an attribute
 * never joins. Registering it fails the test outright ("rendered none of its
 * locale strings") rather than passing vacuously - there is no textContent
 * assertion this subjects list can give it.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    SetupForm::class => static fn (): HTMLObject => new SetupForm(),
    MessageKeyPassphraseForm::class => static fn (): HTMLObject => new MessageKeyPassphraseForm(),
    PasswordResetForm::class => static fn (): HTMLObject => new PasswordResetForm('example-token'),
    PasswordResetRequestForm::class => static fn (): HTMLObject => new PasswordResetRequestForm(),
    EmailRevertForm::class => static fn (): HTMLObject => new EmailRevertForm('example-token'),
    EmailVerifyForm::class => static fn (): HTMLObject => new EmailVerifyForm('example-token'),
    EmailDigestResubscribeForm::class => static fn (): HTMLObject => new EmailDigestResubscribeForm('example-token'),
    EmailDigestSetting::class => static fn (): HTMLObject => new EmailDigestSetting(),

    // A fabricated row rather than a loaded one, same technique
    // AccountMigrationForm's subject uses - selector left null so it can never
    // match a real cookie, which RememberToken::currentSelector() also reads
    // as null outside a real request. The two nulls agreeing marks this row
    // "this device", which exercises that phrasing along with everything else.
    RememberedDevice::class => static function (): HTMLObject {
        $device = new RememberedDevice();
        $device -> tokenId = 1;
        $device -> selector = null;
        $device -> userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0';
        $device -> ipAddress = '203.0.113.5';
        $device -> createdAt = '2026-01-01 00:00:00';
        $device -> lastUsedAt = '2026-01-02 00:00:00';

        return $device;
    },

    LogoutEverywherePanel::class => static fn (): HTMLObject => new LogoutEverywherePanel(),
    LogoutEverywhereButton::class => static fn (): HTMLObject => new LogoutEverywhereButton(),
    GoogleAccountDeleteButton::class => static fn (): HTMLObject => new GoogleAccountDeleteButton(),
    GoogleSignInButton::class => static fn (): HTMLObject => new GoogleSignInButton(),
    PushNotificationSetting::class => static fn (): HTMLObject => new PushNotificationSetting(),
];
