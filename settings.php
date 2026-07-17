<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Settings']);

$page -> addContent(new Heading2('Change Password'));

$page -> addContent(new ChangePasswordForm());

$page -> addContent(new Heading2('Change Email'));

$page -> addContent(new ChangeEmailForm());

$page -> addContent(new Heading2('Two-Factor Authentication'));

$page -> addContent(new TwoFactorSettingsForm(TwoFactor::isEnabled(Auth::user())));

$page -> addContent(new Heading2('Theme'));

$page -> addContent(new ThemeSelector());

$page -> addContent(new Heading2('Remembered Devices'));

$page -> addContent(new RememberedDevicesList((int) Auth::user() -> userId));

// The site needs at least one admin account to function - api/delete-account.php
// rejects userId 1 too, but there's no reason to show the form at all here.
if ((int) Auth::user() -> userId !== 1) {
    $page -> addContent(new Heading2('Delete Account'));

    $page -> addContent(new DeleteAccountForm());
}

$page -> send();
