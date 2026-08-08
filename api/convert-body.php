<?php

declare(strict_types=1);

define('IS_API_REQUEST', true);
require __DIR__ . '/api-init.php';

// The composer's mode selector, answered here rather than in the browser so
// the conversion exists once: the same Markdown/HTMLToDelta pair that reads an
// inbound Fediverse post reads a member's markdown, and DeltaToMarkdown writes
// it back. Two implementations would be two dialects inside a month.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    JSONResponse::error('A body is required', 422) -> send();
}

$body = (string) ($payload['body'] ?? '');
$to = (string) ($payload['to'] ?? '');

// The same ceiling the composer's own body has, applied before any parsing so
// the work is bounded by what was accepted rather than by what was sent.
if (strlen($body) > 262144) {
    JSONResponse::error('That is too long to convert.', 422) -> send();
}

// Switching modes is a person pressing a button, so this is paced for that
// rather than for a loop.
$rate_key = 'convert-body:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::error('Too many changes at once. Please wait a moment.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

if ($to === 'markdown') {
    JSONResponse::success(['body' => DeltaToMarkdown::convert(Delta::sanitize(Delta::decode($body)))]) -> send();
}

if ($to === 'delta') {
    $ops = HTMLToDelta::convert(Markdown::toHTML($body));

    JSONResponse::success(['body' => json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]) -> send();
}

JSONResponse::error('Unknown conversion', 422) -> send();
