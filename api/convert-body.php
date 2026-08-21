<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// The composer's mode selector, answered here rather than in the browser so
// the conversion exists once: the same Markdown/HTMLToDelta pair that reads an
// inbound Fediverse post reads a member's markdown, and DeltaToMarkdown writes
// it back. Two implementations would be two dialects inside a month.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    JSONResponse::localizedError('aBodyIsRequired', 422) -> send();
}

$body = (string) ($payload['body'] ?? '');
$to = (string) ($payload['to'] ?? '');

// The same ceiling the composer's own body has, applied before any parsing so
// the work is bounded by what was accepted rather than by what was sent.
if (strlen($body) > 262144) {
    JSONResponse::localizedError('thatIsTooLongToConvert', 422) -> send();
}

// Switching modes is a person pressing a button, so this is paced for that
// rather than for a loop.
$rate_key = 'convert-body:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 60, 60)) {
    JSONResponse::localizedError('tooManyChangesAtOncePleaseWaitAMoment', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

if ($to === 'markdown') {
    JSONResponse::success(['body' => DeltaToMarkdown::convert(Delta::sanitize(Delta::decode($body)))]) -> send();
}

if ($to === 'delta') {
    $ops = HTMLToDelta::convert(Markdown::toHTML($body));

    JSONResponse::success(['body' => json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)]) -> send();
}

JSONResponse::localizedError('unknownConversion', 422) -> send();
