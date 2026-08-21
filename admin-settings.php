<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

// Site-wide settings are admin-only (the primary admin, userId 1), not general
// moderators - the same gate as every other admin-only action.
if (Auth::id() !== 1) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['adminSettings'] ?? '')]);

$words = Strings::for('AdminSettings');
$page -> addContent(new SettingsSection((string) ($words['services'] ?? ''), new ServicesStatus()));

// How the site is doing, which is a different question from whether the
// daemons are up - so it opens and closes on its own.
$page -> addContent(new SettingsSection((string) ($words['statistics'] ?? ''), new SiteCounters()));

// What the moderators have done. Here rather than on Mod Settings on purpose:
// it is the record of their work, and the person it answers to is the one who
// appointed them.
$page -> addContent(new SettingsSection((string) ($words['moderationLog'] ?? ''), new ModerationActionList()));

// Relays are a subscription this server takes out, which is administration
// rather than moderation - and short enough to read here rather than on a
// page of its own.
$page -> addContent(new SettingsSection((string) ($words['relays'] ?? ''), new RelaysSetting()));

$page -> addContent(new SettingsSection((string) ($words['tests'] ?? ''), new TestSuitePanel()));

$page -> addContent(new SettingsSection((string) ($words['notificationTest'] ?? ''), new NotificationTestPanel()));

$page -> addContent(new SettingsSection((string) ($words['botProtection'] ?? ''), new BotProtectionSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['googleAuth'] ?? ''), new GoogleAuthSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['map'] ?? ''), new MapSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['openRouter'] ?? ''), new OpenRouterSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['mail'] ?? ''), new MailSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['emailDigest'] ?? ''), new EmailDigestSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['favicon'] ?? ''), new FaviconSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['frontPageImage'] ?? ''), new FrontPageImageSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['about'] ?? ''), new AboutSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['termsOfService'] ?? ''), new TermsSettingsForm()));

$page -> addContent(new SettingsSection((string) ($words['privacyPolicy'] ?? ''), new PrivacySettingsForm()));

$page -> send();
