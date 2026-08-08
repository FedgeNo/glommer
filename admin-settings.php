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

$page = new Page(['title' => 'Admin Settings']);

$page -> addContent(new SettingsSection('Services', new ServicesStatus()));

// How the site is doing, which is a different question from whether the
// daemons are up - so it opens and closes on its own.
$page -> addContent(new SettingsSection('Statistics', new SiteCounters()));

// What the moderators have done. Here rather than on Mod Settings on purpose:
// it is the record of their work, and the person it answers to is the one who
// appointed them.
$page -> addContent(new SettingsSection('Moderation Log', new ModerationActionList()));

// Relays are a subscription this server takes out, which is administration
// rather than moderation - and short enough to read here rather than on a
// page of its own.
$page -> addContent(new SettingsSection('Relays', new RelaysSetting()));

$page -> addContent(new SettingsSection('Tests', new TestSuitePanel()));

$page -> addContent(new SettingsSection('Notification Test', new NotificationTestPanel()));

$page -> addContent(new SettingsSection('Bot protection', new BotProtectionSettingsForm()));

$page -> addContent(new SettingsSection('Google Auth', new GoogleAuthSettingsForm()));

$page -> addContent(new SettingsSection('Map', new MapSettingsForm()));

$page -> addContent(new SettingsSection('AI (OpenRouter)', new OpenRouterSettingsForm()));

$page -> addContent(new SettingsSection('Mail', new MailSettingsForm()));

$page -> addContent(new SettingsSection('Favicon', new FaviconSettingsForm()));

$page -> addContent(new SettingsSection('Front Page Image', new FrontPageImageSettingsForm()));

$page -> addContent(new SettingsSection('About', new AboutSettingsForm()));

$page -> addContent(new SettingsSection('Terms of Service', new TermsSettingsForm()));

$page -> addContent(new SettingsSection('Privacy Policy', new PrivacySettingsForm()));

$page -> send();
