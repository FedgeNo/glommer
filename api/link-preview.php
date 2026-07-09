<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$rate_key = 'link-preview:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 15, 60)) {
    JSONResponse::error('Too many requests. Please try again later.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$payload = json_decode((string) file_get_contents('php://input'), true);
$url = trim((string) ($payload['url'] ?? ''));

if ($url === '' || strlen($url) > 255 || !preg_match('/^https?:\/\//i', $url)) {
    JSONResponse::error('Invalid URL', 422) -> send();
}

$preview = LinkPreviewFetcher::fetch($url);

JSONResponse::success([
    'title' => $preview['title'] ?? null,
    'description' => $preview['description'] ?? null,
    'image' => $preview['image'] ?? null,
]) -> send();
