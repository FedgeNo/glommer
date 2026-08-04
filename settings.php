<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Settings']);

$page -> addContent(new SettingsSection('Change Password', new PasswordChangeForm()));

$page -> addContent(new SettingsSection('Change Email', new EmailChangeForm()));

$page -> addContent(new SettingsSection('Two-Factor Authentication', new TwoFactorSettingsForm(TwoFactor::isEnabled(Auth::user()))));

$page -> addContent(new SettingsSection('Theme', new ThemeSelector()));

$page -> addContent(new SettingsSection('Sensitive Media', new SensitiveMediaSetting()));

$page -> addContent(new SettingsSection('Remembered Devices', new RememberedDeviceList(['userId' => (int) Auth::user() -> userId])));

$page -> addContent(new SettingsSection('Sessions', new LogoutEverywherePanel()));

// The check negotiates for real, so it needs the same ICE configuration a call
// would use - anything else would be testing a different thing than it reports.
$page -> addContent(new JSGlobals(['iceServers' => VideoCall::iceServers()]));

$page -> addContent(new SettingsSection('Video Calling', new VideoCallTestPanel()));

$page -> addContent(new SettingsSection('Fediverse', new RemoteFollowsForm(RemoteFollow::listForUser((int) Auth::user() -> userId))));

$page -> addContent(new SettingsSection('Moving Servers', new AccountMigrationForm()));

// The site needs at least one admin account to function - api/delete-account.php
// rejects userId 1 too, but there's no reason to show the form at all here.
if ((int) Auth::user() -> userId !== 1) {
    $page -> addContent(new SettingsSection('Delete Account', new AccountDeleteForm()));
}

$page -> send();
