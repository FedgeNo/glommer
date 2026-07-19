<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Settings']);

$page -> addContent(new SettingsSection('Change Password', new ChangePasswordForm()));

$page -> addContent(new SettingsSection('Change Email', new ChangeEmailForm()));

$page -> addContent(new SettingsSection('Two-Factor Authentication', new TwoFactorSettingsForm(TwoFactor::isEnabled(Auth::user()))));

$page -> addContent(new SettingsSection('Theme', new ThemeSelector()));

$page -> addContent(new SettingsSection('Remembered Devices', new RememberedDevicesList((int) Auth::user() -> userId)));

$page -> addContent(new SettingsSection('Fediverse', new RemoteFollowsForm(RemoteFollow::listForUser((int) Auth::user() -> userId))));

// The site needs at least one admin account to function - api/delete-account.php
// rejects userId 1 too, but there's no reason to show the form at all here.
if ((int) Auth::user() -> userId !== 1) {
    $page -> addContent(new SettingsSection('Delete Account', new DeleteAccountForm()));
}

$page -> send();
