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
    } elseif (isset($_POST['googleAuthClientId']) || isset($_POST['googleAuthSecret'])) {
        $client_id = trim((string) ($_POST['googleAuthClientId'] ?? ''));
        $secret = trim((string) ($_POST['googleAuthSecret'] ?? ''));

        Settings::set(GoogleAuth::CLIENT_ID_SETTING, $client_id);

        // Write-only, same as the Turnstile secret: a blank field keeps the
        // stored secret rather than clearing it.
        if ($secret !== '') {
            Settings::set(GoogleAuth::CLIENT_SECRET_SETTING, $secret);
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
    } elseif (isset($_POST['smtpHost']) || isset($_POST['smtpPort']) || isset($_POST['smtpUsername']) || isset($_POST['smtpPassword']) || isset($_POST['smtpEncryption'])) {
        $smtp_host = trim((string) ($_POST['smtpHost'] ?? ''));
        $smtp_port = trim((string) ($_POST['smtpPort'] ?? ''));
        $smtp_username = trim((string) ($_POST['smtpUsername'] ?? ''));
        $smtp_password = (string) ($_POST['smtpPassword'] ?? '');
        $smtp_encryption = (string) ($_POST['smtpEncryption'] ?? 'tls');

        Settings::set(Mailer::SMTP_HOST_SETTING, $smtp_host);
        Settings::set(Mailer::SMTP_PORT_SETTING, $smtp_port);
        Settings::set(Mailer::SMTP_USERNAME_SETTING, $smtp_username);
        Settings::set(Mailer::SMTP_ENCRYPTION_SETTING, $smtp_encryption);

        // Write-only, same as the Turnstile/Google Auth secrets: a blank
        // field keeps the stored password rather than clearing it.
        if ($smtp_password !== '') {
            Settings::set(Mailer::SMTP_PASSWORD_SETTING, $smtp_password);
        }

        $saved = true;
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
    $page -> addContent(new Notice('Settings saved.'));
}

if ($errors !== []) {
    $page -> addContent(new ErrorList($errors));
}

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
