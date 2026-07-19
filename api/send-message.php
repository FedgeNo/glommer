<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = DB::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$recipient_id = (int) ($payload['recipientId'] ?? $_POST['recipientId'] ?? 0);
$body = trim((string) ($payload['body'] ?? $_POST['body'] ?? ''));

if ($body === '') {
    JSONResponse::error('Message cannot be empty', 422) -> send();
}

if (strlen($body) > 65535) {
    JSONResponse::error('Message is too long', 422) -> send();
}

if ($recipient_id === $current_user -> userId) {
    JSONResponse::error('You can\'t message yourself.', 422) -> send();
}

$recipient = User::load($recipient_id);

if ($recipient === null || $recipient -> banned) {
    JSONResponse::error('User not found', 404) -> send();
}

if (Block::exists($current_user -> userId, $recipient_id)) {
    JSONResponse::error('Unable to send message.', 403) -> send();
}

// Independent of the per-recipient throttle below - this one catches a
// single account blasting many DIFFERENT people (mass spam), which a
// per-recipient cap alone would never see since each recipient only gets a
// handful of messages. 100 messages/10min is generous for a genuinely fast
// back-and-forth conversation but bounds a spam blast to under a couple
// hundred sends before it has to slow down.
$spam_rate_key = 'send-message:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($spam_rate_key, 100, 600)) {
    JSONResponse::error('Too many messages sent in a short time. Please wait a bit and try again.', 429) -> send();
}

// Locked around the check and the insert together - otherwise two requests
// fired in parallel can both read a count under the throttle before either's
// insert lands, letting a client bypass it just by not waiting for a
// response (same race RateLimiter itself guards against for login/password
// attempts).
$throttle_key = 'message-throttle:' . $current_user -> userId . ':' . $recipient_id;
RateLimiter::acquireLock($throttle_key);

if (Message::unansweredCount($current_user -> userId, $recipient_id) >= Message::MAX_UNANSWERED) {
    RateLimiter::releaseLock($throttle_key);
    JSONResponse::error('You\'ve sent a lot of messages without a reply - wait for them to respond before sending more.', 429) -> send();
}

DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`)
    VALUES (?, ?, ?)
', 'iis', $current_user -> userId, $recipient_id, $body);
$message_id = (int) mysqli_insert_id($mysqli);
RateLimiter::releaseLock($throttle_key);
RateLimiter::recordAttempt($spam_rate_key);

Notification::create($recipient_id, $current_user -> userId, 'message');

$message_payload = [
    'messageId' => $message_id,
    'senderId' => $current_user -> userId,
    'recipientId' => $recipient_id,
    'body' => $body,
    'createdAt' => date('Y-m-d H:i:s'),
];

WebSocketPusher::push($recipient_id, [
    'event' => 'message',
    'message' => $message_payload,
]);

JSONResponse::success($message_payload) -> send();
