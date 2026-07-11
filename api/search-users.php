<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$query = trim((string) ($_GET['q'] ?? ''));

if ($query === '') {
    $candidates = $current_user -> getSuggestedUsers();
} else {
    // Escape LIKE wildcards so a literal % or _ in the query doesn't match everything.
    $like = '%' . addcslashes($query, '\\%_') . '%';
    $not_banned = 0;
    $limit = 25;

    $stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Users`
    WHERE (`username` LIKE ? OR `displayName` LIKE ?) AND `userId` != ? AND `banned` = ?
    LIMIT ?
');
    mysqli_stmt_bind_param($stmt, 'ssiii', $like, $like, $current_user -> userId, $not_banned, $limit);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    $candidates = [];

    while ($row = mysqli_fetch_assoc($result)) {
        $candidates[] = User::fromRow($row);
    }
}

$users = [];

foreach ($candidates as $candidate) {
    $user_id = (int) $candidate -> userId;
    $blocked_by_viewer = Block::blockedBy($current_user -> userId, $user_id);
    $blocked_by_other = Block::blockedBy($user_id, $current_user -> userId);
    $friendship = ($blocked_by_viewer || $blocked_by_other) ? null : Friendship::statusBetween($current_user -> userId, $user_id);

    $users[] = [
        'userId' => $user_id,
        'username' => $candidate -> username,
        'displayName' => $candidate -> displayName,
        'image' => $candidate -> avatarURL(),
        'createdAt' => $candidate -> createdAt,
        'blockedByViewer' => $blocked_by_viewer,
        'blockedByOther' => $blocked_by_other,
        'friendshipStatus' => $friendship ?-> status,
        'friendshipSentByViewer' => $friendship !== null ? ((int) $friendship -> requesterId === $current_user -> userId) : null,
        'isMod' => (bool) $candidate -> isMod,
    ];
}

JSONResponse::success(['users' => $users]) -> send();
