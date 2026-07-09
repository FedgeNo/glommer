<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$other_user_id = (int) ($_GET['otherUserId'] ?? 0);
$before_message_id = (int) ($_GET['beforeMessageId'] ?? 0);

if ($other_user_id === 0 || $before_message_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if (Block::exists($current_user -> userId, $other_user_id)) {
    JSONResponse::error('You can\'t message this user.', 403) -> send();
}

['rows' => $rows, 'hasMore' => $has_more] = Message::rowsBetween($current_user -> userId, $other_user_id, 20, $before_message_id);

JSONResponse::success([
    'messages' => $rows,
    'hasMore' => $has_more,
]) -> send();
