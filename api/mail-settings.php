<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$smtp_host = trim((string) ($payload['smtpHost'] ?? ''));
$smtp_port = trim((string) ($payload['smtpPort'] ?? ''));
$smtp_username = trim((string) ($payload['smtpUsername'] ?? ''));
$smtp_password = (string) ($payload['smtpPassword'] ?? '');
$smtp_encryption = (string) ($payload['smtpEncryption'] ?? 'tls');

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
