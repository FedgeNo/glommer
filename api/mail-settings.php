<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$mail_from_address = trim((string) ($payload['mailFromAddress'] ?? ''));
$mail_from_name = trim((string) ($payload['mailFromName'] ?? ''));
$smtp_host = trim((string) ($payload['smtpHost'] ?? ''));
$smtp_port = trim((string) ($payload['smtpPort'] ?? ''));
$smtp_username = trim((string) ($payload['smtpUsername'] ?? ''));
$smtp_password = (string) ($payload['smtpPassword'] ?? '');
$smtp_encryption = (string) ($payload['smtpEncryption'] ?? 'tls');

// Blank leaves the stored address unchanged - not write-only like the
// password, but a blank address would break every subsequent email, so it
// must never silently apply (see MailSettingsForm's docblock).
if ($mail_from_address !== '') {
    if (filter_var($mail_from_address, FILTER_VALIDATE_EMAIL) === false) {
        JSONResponse::error('That is not a valid email address.', 422) -> send();
    }

    Settings::set(Mailer::FROM_ADDRESS_SETTING, $mail_from_address);
}

Settings::set(Mailer::FROM_NAME_SETTING, $mail_from_name);
Settings::set(Mailer::SMTP_HOST_SETTING, $smtp_host);
Settings::set(Mailer::SMTP_PORT_SETTING, $smtp_port);
Settings::set(Mailer::SMTP_USERNAME_SETTING, $smtp_username);
Settings::set(Mailer::SMTP_ENCRYPTION_SETTING, $smtp_encryption);

// Write-only, same as the Turnstile/Google Auth secrets: a blank field keeps
// the stored password rather than clearing it.
if ($smtp_password !== '') {
    Settings::set(Mailer::SMTP_PASSWORD_SETTING, $smtp_password);
}

JSONResponse::success(['saved' => true]) -> send();
