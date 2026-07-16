<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$query = trim((string) ($payload['q'] ?? ''));
$before_user_id = (int) ($payload['beforeUserId'] ?? 0);
$has_more = false;
$oldest_user_id = null;

if ($query === '') {
    // The empty-query suggestion list isn't cursor-paginated - it's a fixed,
    // ranked set (mutual-friend count, falling back to random), not a simple
    // newest-first query a keyset cursor could walk.
    $candidates = $current_user -> getSuggestedUsers();
} else {
    // Escape LIKE wildcards so a literal % or _ in the query doesn't match everything.
    $like = '%' . addcslashes($query, '\\%_') . '%';
    $not_banned = 0;
    $limit = 20;
    $fetch_limit = $limit + 1;

    $stmt = DB::run('
SELECT *
    FROM `Users`
    WHERE (`username` LIKE ? OR `displayName` LIKE ?) AND `userId` != ? AND `banned` = ?
        AND (? = 0 OR `userId` < ?)
        AND NOT EXISTS (
            SELECT 1
                FROM `Blocks` `b`
                WHERE (`b`.`blockerId` = ? AND `b`.`blockedId` = `Users`.`userId`) OR (`b`.`blockerId` = `Users`.`userId` AND `b`.`blockedId` = ?)
        )
    ORDER BY `userId` DESC
    LIMIT ?
', 'ssiiiiiii', $like, $like, $current_user -> userId, $not_banned, $before_user_id, $before_user_id, $current_user -> userId, $current_user -> userId, $fetch_limit);
    $result = mysqli_stmt_get_result($stmt);

    $candidates = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $candidates[] = User::fromRow($row);
    }

    $has_more = count($candidates) > $limit;

    if ($has_more) {
        array_pop($candidates);
    }

    if ($candidates !== []) {
        $oldest_user_id = (int) $candidates[count($candidates) - 1] -> userId;
    }
}

$users = [];

foreach ($candidates as $candidate) {
    $users[] = OtherUser::payloadFor($candidate, $current_user);
}

JSONResponse::success([
    'users' => $users,
    'hasMore' => $has_more,
    'oldestUserId' => $oldest_user_id,
]) -> send();
