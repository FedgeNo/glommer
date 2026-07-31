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

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$other_user_id = (int) ($payload['otherUserId'] ?? 0);
$type = (string) ($payload['type'] ?? '');
$signal = $payload['signal'] ?? null;

if ($other_user_id === 0 || $other_user_id === (int) $current_user -> userId || !VideoCall::isSignalType($type)) {
    JSONResponse::error('Invalid request', 422) -> send();
}

// The session descriptions and candidates inside are the two browsers' business,
// not this server's - it only checks that there isn't an unreasonable amount of
// it before handing it on.
if (strlen((string) json_encode($signal)) > VideoCall::MAX_SIGNAL_BYTES) {
    JSONResponse::error('Signal too large', 422) -> send();
}

if (Block::exists((int) $current_user -> userId, $other_user_id)) {
    JSONResponse::error('Unable to call this user.', 403) -> send();
}

// A call only exists while both people have the thread open, so there is no
// case where a signal should reach someone who has walked away - and refusing
// here is what stops this becoming a way to make a stranger's browser negotiate
// with yours.
if (!ChatPresence::isPresentWith($other_user_id, (int) $current_user -> userId)) {
    JSONResponse::error('They are no longer in this conversation.', 409) -> send();
}

WebSocketPusher::push($other_user_id, [
    'event' => 'call',
    'call' => [
        'type' => $type,
        'fromUserId' => (int) $current_user -> userId,
        'signal' => $signal,
    ],
]);

JSONResponse::success(['sent' => true]) -> send();
