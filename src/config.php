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
];
