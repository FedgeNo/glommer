<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
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

$stmt = mysqli_prepare($mysqli, '
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`)
    VALUES (?, ?, ?)
');
mysqli_stmt_bind_param($stmt, 'iis', $current_user -> userId, $recipient_id, $body);
mysqli_stmt_execute($stmt);
$message_id = (int) mysqli_insert_id($mysqli);

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
