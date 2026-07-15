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

$page = Page::create('Site Settings');

$page -> addContent(new Heading2('Upload worker'));
$page -> addContent(new UploadWorkerStatus());

$page -> addContent(new Heading2('Bot protection'));
$page -> addContent(new AdminSettingsForm());

$page -> addContent(new Heading2('Google Sign-In'));
$page -> addContent(new GoogleAuthSettingsForm());

$page -> addContent(new Heading2('Mail'));
$page -> addContent(new MailSettingsForm());

$page -> addContent(new Heading2('Favicon'));
$page -> addContent(new FaviconSettingsForm());

$page -> addContent(new Heading2('Terms of Service'));
$page -> addContent(new TermsSettingsForm());

$page -> addContent(new Heading2('Privacy Policy'));
$page -> addContent(new PrivacySettingsForm());

$page -> send();
