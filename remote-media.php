<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Signed in only, matching the remote posts and profiles this media belongs to
// - none of that is public-facing content this site represents, and gating it
// also keeps the proxy from being something the open internet can spend this
// server's bandwidth through.
//
// A status rather than Auth::requireLogin()'s redirect to the login page: this
// is a subresource of a page, and an <img> that follows a redirect only ends up
// rendering the login page as a broken image.
if (!Auth::check()) {
    http_response_code(403);
    exit;
}

$item_id = (int) ($_GET['item'] ?? 0);

if ($item_id <= 0) {
    http_response_code(404);
    exit;
}

RemoteMedia::serve($item_id);
