<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Signed in only, matching the remote profiles this picture belongs to - none
// of that is public-facing content this site represents, and gating it also
// keeps the proxy from being something the open internet can spend this
// server's bandwidth through.
//
// A status rather than Auth::requireLogin()'s redirect to the login page: this
// is a subresource of a page, and an <img> that follows a redirect only ends up
// rendering the login page as a broken image.
if (!Auth::check()) {
    http_response_code(403);
    exit;
}

$user_id = (int) ($_GET['user'] ?? 0);

if ($user_id <= 0) {
    http_response_code(404);
    exit;
}

RemoteAvatar::serve($user_id);
