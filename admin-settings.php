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

$saved = false;
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Each section is its own form with its own save button - which one was
    // submitted shows in which fields arrived.
    if (isset($_POST['turnstileSiteKey']) || isset($_POST['turnstileSecretKey'])) {
        $site_key = trim((string) ($_POST['turnstileSiteKey'] ?? ''));
        $secret_key = trim((string) ($_POST['turnstileSecretKey'] ?? ''));

        Settings::set(Turnstile::SITE_KEY_SETTING, $site_key);

        // The secret key is write-only: a blank field means "leave the stored secret
        // unchanged" (it's never rendered back into the form), so only overwrite it
        // when an actual value is submitted.
        if ($secret_key !== '') {
            Settings::set(Turnstile::SECRET_KEY_SETTING, $secret_key);
        }

        $saved = true;
    } elseif (isset($_FILES['favicon'])) {
        if ($_FILES['favicon']['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'The favicon upload failed. Please try again.';
        } elseif (!Favicon::updateFromUpload($_FILES['favicon']['tmp_name'])) {
            $errors[] = 'That file could not be read as an image.';
        } else {
            $saved = true;
        }
    } elseif (isset($_POST[SitePolicy::TERMS_SETTING])) {
        Settings::set(SitePolicy::TERMS_SETTING, trim((string) $_POST[SitePolicy::TERMS_SETTING]));
        $saved = true;
    } elseif (isset($_POST[SitePolicy::PRIVACY_SETTING])) {
        Settings::set(SitePolicy::PRIVACY_SETTING, trim((string) $_POST[SitePolicy::PRIVACY_SETTING]));
        $saved = true;
    }
}

$page = Page::create('Site Settings');

if ($saved) {
    $page -> addContents(new Notice('Settings saved.'));
}

if ($errors !== []) {
    $page -> addContents(new ErrorList($errors));
}

$page -> addContents(new Heading2('Bot protection'));
$page -> addContents(new AdminSettingsForm());

$page -> addContents(new Heading2('Favicon'));
$page -> addContents(new FaviconSettingsForm());

$page -> addContents(new Heading2('Terms of Service'));
$page -> addContents(new TermsSettingsForm());

$page -> addContents(new Heading2('Privacy Policy'));
$page -> addContents(new PrivacySettingsForm());

$page -> send();
