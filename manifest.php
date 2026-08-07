<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// The install manifest. PHP rather than a static file because the site's own
// name and icon are config, never hardcoded - everything else here is fixed.
header('Content-Type: application/manifest+json');

$title = (string) Config::get('siteTitle');

echo json_encode([
    'name' => $title,
    'short_name' => $title,
    'start_url' => ServerURL::absolute('/'),
    'scope' => ServerURL::absolute('/'),
    'display' => 'standalone',
    'icons' => [
        [
            'src' => Favicon::URL(),
            'sizes' => '512x512',
            'type' => 'image/png',
            'purpose' => 'any',
        ],
    ],
], JSON_UNESCAPED_SLASHES);
