<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the emoji shortcode table, as the JavaScript module the client half
// of the expansion imports. Served from the one hard-coded copy in
// EmojiShortcodeMap rather than kept as a second file, so the server and the
// browser cannot drift apart about what a name means.
//
// The body is data encoded as JSON inside one assignment; nothing about it is
// assembled from anything but that constant.
$version = EmojiShortcodeMap::version();

header('Content-Type: text/javascript; charset=UTF-8');

// It changes only when the table itself is edited, so it can be cached hard and
// revalidated cheaply. ETag rather than a far-future expiry, because the URL has
// no version in it and a stale copy would be wrong until the cache aged out.
header('ETag: "' . $version . '"');
header('Cache-Control: public, max-age=86400, must-revalidate');

if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? ''), '"') === $version) {
    http_response_code(304);
    exit;
}

echo EmojiShortcodeMap::javaScriptModule();
