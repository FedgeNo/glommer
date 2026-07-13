<?php

declare(strict_types=1);

return [
    'host' => Env::get('DB_HOST', '127.0.0.1'),
    'port' => (int) Env::get('DB_PORT', '3306'),
    'database' => Env::get('DB_DATABASE', 'glommer'),
    'username' => Env::get('DB_USERNAME', 'glommer'),
    'password' => Env::get('DB_PASSWORD', 'change-me'),
    // Update these once a real domain is in place - deliverability depends on
    // this address's domain having matching SPF/DKIM/DMARC DNS records.
    'mailFromAddress' => Env::get('MAIL_FROM_ADDRESS', 'noreply@example.com'),
    'mailFromName' => Env::get('MAIL_FROM_NAME', 'Glommer'),
    // Optional SMTP relay. With SMTP_HOST set, Mailer speaks SMTP to it
    // (recommended - PHP's mail() hands off to the local sendmail, which on a
    // typical VPS lands straight in spam folders); left empty, mail() is used.
    // SMTP_ENCRYPTION: 'tls' (STARTTLS, usual on port 587), 'ssl' (implicit
    // TLS, usual on port 465), or 'none'.
    'SMTPHost' => Env::get('SMTP_HOST', ''),
    'SMTPPort' => (int) Env::get('SMTP_PORT', '587'),
    'SMTPUsername' => Env::get('SMTP_USERNAME', ''),
    'SMTPPassword' => Env::get('SMTP_PASSWORD', ''),
    'SMTPEncryption' => Env::get('SMTP_ENCRYPTION', 'tls'),
    'siteURL' => Env::get('SITE_URL', 'https://example.com'),
    'siteTitle' => Env::get('SITE_TITLE', 'Glommer'),
    // The WebSocket daemon (bin/websocket-server.php) is a separate long-running
    // process from Apache/PHP-FPM - these let both sides agree on where it
    // lives and share a secret for signing/verifying connection tokens and
    // authenticating the internal push channel between them.
    'WSHost' => Env::get('WS_HOST', '0.0.0.0'),
    'WSPort' => (int) Env::get('WS_PORT', '8090'),
    'WSPushPort' => (int) Env::get('WS_PUSH_PORT', '8091'),
    // No usable default: an unset OR still-placeholder ('change-me') secret
    // resolves to null, so WS auth fails closed (WSToken and the push channel
    // reject a null secret) instead of running on a value anyone can read from
    // .env.example or this file. A real install always has a random one.
    'WSSecret' => in_array($ws = Env::get('WS_SECRET', ''), ['', 'change-me'], true) ? null : $ws,
    'WSTLSCert' => Env::get('WS_TLS_CERT', '') ?: null,
    'WSTLSKey' => Env::get('WS_TLS_KEY', '') ?: null,
];
