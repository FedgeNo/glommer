<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check() || !Auth::canModerate()) {
    JSONResponse::error('Not authorized', 403) -> send();
}

$before_user_id = isset($_GET['beforeUserId']) && $_GET['beforeUserId'] !== '' ? (int) $_GET['beforeUserId'] : null;
$limit = BannedUserList::PAGE_SIZE;

$items = BannedUserList::fetch($limit + 1, $before_user_id);
$has_more = count($items) > $limit;

if ($has_more) {
    array_pop($items);
}

$payloads = [];

foreach ($items as $item) {
    $payloads[] = BannedUser::payloadFor($item);
}

JSONResponse::success([
    'items' => $payloads,
    'hasMore' => $has_more,
    'oldestUserId' => $items !== [] ? (int) end($items) -> userId : null,
]) -> send();
