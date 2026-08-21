<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => (string) (Strings::for('PageTitle')['userSettings'] ?? '')]);

$words = Strings::for('UserSettings');

$page -> addContent(new SettingsSection((string) ($words['changePassword'] ?? ''), new PasswordChangeForm()));

$page -> addContent(new SettingsSection((string) ($words['changeEmail'] ?? ''), new EmailChangeForm()));

$page -> addContent(new SettingsSection((string) ($words['twoFactorAuthentication'] ?? ''), new TwoFactorSettingsForm(TwoFactor::isEnabled(Auth::user()))));

$page -> addContent(new SettingsSection((string) (Strings::for('LanguageSelector')['legend'] ?? ''), new LanguageSelector()));

$page -> addContent(new SettingsSection((string) ($words['theme'] ?? ''), new ThemeSelector()));

$page -> addContent(new SettingsSection((string) ($words['sensitiveMedia'] ?? ''), new SensitiveMediaSetting()));

$page -> addContent(new SettingsSection((string) ($words['encryptedMessages'] ?? ''), new EncryptedMessagesSetting()));

$page -> addContent(new SettingsSection((string) ($words['emailDigests'] ?? ''), new EmailDigestSetting()));

if (WebPushKeys::isConfigured()) {
    $page -> addContent(new SettingsSection((string) ($words['pushNotifications'] ?? ''), new PushNotificationSetting()));
}

$page -> addContent(new SettingsSection((string) ($words['rememberedDevices'] ?? ''), new RememberedDeviceList(['userId' => (int) Auth::user() -> userId])));

$page -> addContent(new SettingsSection((string) ($words['sessions'] ?? ''), new LogoutEverywherePanel()));

$page -> addContent(new SettingsSection((string) ($words['videoCalling'] ?? ''), new VideoCallTestPanel()));

$page -> addContent(new SettingsSection((string) ($words['fediverse'] ?? ''), new RemoteFollowsForm(RemoteFollow::listForUser((int) Auth::user() -> userId))));

$page -> addContent(new SettingsSection((string) ($words['movingServers'] ?? ''), new AccountMigrationForm()));

// The site needs at least one admin account to function - api/delete-account.php
// rejects userId 1 too, but there's no reason to show the form at all here.
if ((int) Auth::user() -> userId !== 1) {
    $page -> addContent(new SettingsSection((string) ($words['deleteAccount'] ?? ''), new AccountDeleteForm()));
}

$page -> send();
