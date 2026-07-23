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

// Linked, not embedded: running the suite takes a few seconds, so it lives on
// its own page rather than delaying this one every time it loads.
$tests_panel = new Div();
$tests_panel -> class = 'Card d-flex flex-column gap-2 align-items-start';
$tests_panel -> addContent(new Paragraph('Run the site\'s test suite and see the results. It takes a few seconds, so it opens on its own page.'));

$tests_link = new Anchor(ServerURL::absolute('/admin/tests'), 'Run tests');
$tests_link -> class = 'Button';
$tests_panel -> addContent($tests_link);

$page -> addContent(new SettingsSection('Tests', $tests_panel));

$page -> addContent(new SettingsSection('Bot protection', new AdminSettingsForm()));

$page -> addContent(new SettingsSection('Google Sign-In', new GoogleAuthSettingsForm()));

$page -> addContent(new SettingsSection('Mail', new MailSettingsForm()));

$page -> addContent(new SettingsSection('Favicon', new FaviconSettingsForm()));

$page -> addContent(new SettingsSection('About', new AboutSettingsForm()));

$page -> addContent(new SettingsSection('Terms of Service', new TermsSettingsForm()));

$page -> addContent(new SettingsSection('Privacy Policy', new PrivacySettingsForm()));

$page -> send();
