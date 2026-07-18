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

$page = new Page(['title' => 'Site Settings']);

$page -> addContent(new SettingsSection('Services', new ServicesStatus()));

$page -> addContent(new SettingsSection('Bot protection', new AdminSettingsForm()));

$page -> addContent(new SettingsSection('Google Sign-In', new GoogleAuthSettingsForm()));

$page -> addContent(new SettingsSection('Mail', new MailSettingsForm()));

$page -> addContent(new SettingsSection('Favicon', new FaviconSettingsForm()));

$page -> addContent(new SettingsSection('About', new AboutSettingsForm()));

$page -> addContent(new SettingsSection('Terms of Service', new TermsSettingsForm()));

$page -> addContent(new SettingsSection('Privacy Policy', new PrivacySettingsForm()));

$page -> send();
