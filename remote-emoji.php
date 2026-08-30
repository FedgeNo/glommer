<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Remote posts are members-only, and keeping this proxy behind the same gate
// prevents the open internet from spending the server's fetch bandwidth.
if (!Auth::check()) {
    http_response_code(403);
    exit;
}

$emoji_id = (int) ($_GET['emoji'] ?? 0);

if ($emoji_id <= 0) {
    http_response_code(404);
    exit;
}

RemoteEmoji::serve($emoji_id);
