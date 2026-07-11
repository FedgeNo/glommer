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
    'siteURL' => Env::get('SITE_URL', 'https://example.com'),
    'siteTitle' => Env::get('SITE_TITLE', 'Glommer'),
    // The WebSocket daemon (bin/websocket-server.php) is a separate long-running
    // process from Apache/PHP-FPM - these let both sides agree on where it
    // lives and share a secret for signing/verifying connection tokens and
    // authenticating the internal push channel between them.
    'WSHost' => Env::get('WS_HOST', '0.0.0.0'),
    'WSPort' => (int) Env::get('WS_PORT', '8090'),
    'WSPushPort' => (int) Env::get('WS_PUSH_PORT', '8091'),
    'WSSecret' => Env::get('WS_SECRET', 'change-me'),
    'WSTLSCert' => Env::get('WS_TLS_CERT', '') ?: null,
    'WSTLSKey' => Env::get('WS_TLS_KEY', '') ?: null,
];
