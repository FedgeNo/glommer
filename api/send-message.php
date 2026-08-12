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
$recipient_id = (int) ($payload['recipientId'] ?? 0);
$body = trim((string) ($payload['body'] ?? ''));
$envelope = null;

// An encrypted message arrives as an envelope instead of a body - ciphertext
// this server relays without being able to read (see MessageEnvelope). The
// two are exclusive: a message is one or the other.
if (isset($payload['envelope'])) {
    if ($body !== '') {
        JSONResponse::error('A message is either plaintext or encrypted, not both.', 422) -> send();
    }

    $envelope = MessageEnvelope::normalize((string) $payload['envelope']);

    if ($envelope === null) {
        JSONResponse::error('Malformed encrypted message.', 422) -> send();
    }

    if (!MessageFranking::isConfigured()) {
        JSONResponse::error('Encrypted messaging is not available on this server.', 422) -> send();
    }
} elseif ($body === '') {
    JSONResponse::fieldError('body', 'Write something first.') -> send();
}

if (strlen($body) > 65535) {
    JSONResponse::fieldError('body', 'That message is too long.') -> send();
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

// Encryption is a property of the pair, not the sender: the envelope's
// wrapped key is only openable by someone holding one of the two ECDH private
// keys, so a recipient without a published public key could never read it -
// and a remote recipient can never take one at all, because a federated
// message leaves here as ActivityPub, which has no encryption to speak.
if ($envelope !== null && ($recipient -> remoteActorURI !== null || $recipient -> messagePublicKey === null || $current_user -> messagePublicKey === null)) {
    JSONResponse::error('This conversation can\'t take encrypted messages.', 422) -> send();
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

// The franking tag is the server's commitment, made at relay time, that this
// exact ciphertext passed between these two people - what lets a report of an
// encrypted message be verified later. See MessageFranking.
$franking_tag = $envelope !== null ? MessageFranking::tag($current_user -> userId, $recipient_id, $envelope) : null;
$stored_body = $envelope !== null ? null : $body;

DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`, `bodyCiphertext`, `frankingTag`)
    VALUES (?, ?, ?, ?, ?)
', 'iisss', $current_user -> userId, $recipient_id, $stored_body, $envelope, $franking_tag);
$message_id = (int) mysqli_insert_id(DB::connection());
RateLimiter::releaseLock($throttle_key);
RateLimiter::recordAttempt($spam_rate_key);

Notification::create($recipient_id, $current_user -> userId, 'message');

$message_payload = [
    'messageId' => $message_id,
    'senderId' => $current_user -> userId,
    'recipientId' => $recipient_id,
    'body' => $stored_body,
    'bodyCiphertext' => $envelope,
    'createdAt' => date('Y-m-d H:i:s'),
    'sender' => [
        'slug' => $current_user -> slug,
        'title' => $current_user -> title,
        'image' => $current_user -> avatarURL(),
    ],
];

// A remote recipient is reached over the network instead of over the socket -
// there is no connection here to push down.
if ($recipient -> remoteActorURI !== null) {
    ActivityPubMessage::publish($message_id, $current_user, $recipient, $body);
} else {
    WebSocketPusher::push($recipient_id, [
        'event' => 'message',
        'message' => $message_payload,
    ]);
}

JSONResponse::success($message_payload) -> send();
