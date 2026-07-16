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

$feed_type = (string) ($payload['feedType'] ?? 'global');
$before_post_id = (int) ($payload['beforePostId'] ?? 0);
$profile_user_id = (int) ($payload['userId'] ?? 0);
$tag = strtolower(trim((string) ($payload['tag'] ?? '')));

if ($before_post_id === 0 || !in_array($feed_type, ['global', 'friends', 'user', 'tag'], true)) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if ($feed_type === 'friends' && !Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

if ($feed_type === 'user' && $profile_user_id === 0) {
    JSONResponse::error('Invalid request', 422) -> send();
}

if ($feed_type === 'tag' && !preg_match('/^[a-z0-9_]{1,50}$/', $tag)) {
    JSONResponse::error('Invalid request', 422) -> send();
}

$mysqli = DB::connection();
$limit = 20;

if ($feed_type === 'friends') {
    $current_user = Auth::user();

    ['rows' => $feed_rows, 'hasMore' => $has_more] = Timeline::rowsForUser((int) $current_user -> userId, $limit, $before_post_id);
} elseif ($feed_type === 'user') {
    $fetch_limit = $limit + 1;
    $not_banned = 0;

    // Same banned gate user.php itself 404s on - a banned profile's older
    // posts shouldn't be fetchable via this endpoint just because the page
    // that would normally show them isn't reachable.
    $feed_stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Posts`.`userId` = ? AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
    mysqli_stmt_bind_param($feed_stmt, 'iiii', $profile_user_id, $not_banned, $before_post_id, $fetch_limit);
    mysqli_stmt_execute($feed_stmt);
    $feed_result = mysqli_stmt_get_result($feed_stmt);

    $feed_rows = [];

    while ($row = mysqli_fetch_assoc($feed_result)) {
        $feed_rows[] = $row;
    }

    $has_more = count($feed_rows) > $limit;

    if ($has_more) {
        array_pop($feed_rows);
    }
} elseif ($feed_type === 'tag') {
    ['rows' => $feed_rows, 'hasMore' => $has_more] = Hashtag::postRows($tag, $limit, $before_post_id);
} else {
    ['rows' => $feed_rows, 'hasMore' => $has_more] = Post::globalFeedRows($limit, $before_post_id);
}

$viewer_id = Auth::id();

$posts = Post::fromRowsWithItems($feed_rows);
$post_ids = array_map(fn ($post) => (int) $post -> postId, $posts);

$reply_counts = Post::replyCountsForPosts($post_ids);
$like_counts = Post::likeCountsForPosts($post_ids);
$liked = $viewer_id !== null ? Post::likedByUserForPosts($post_ids, (int) $viewer_id) : [];
$bookmarked = $viewer_id !== null ? Bookmark::bookmarkedByUserForPosts($post_ids, (int) $viewer_id) : [];

$post_payloads = [];

foreach ($posts as $post) {
    $post_id = (int) $post -> postId;

    $post_payloads[] = $post -> toPayload(
        $reply_counts[$post_id] ?? 0,
        $like_counts[$post_id] ?? 0,
        $liked[$post_id] ?? false,
        $bookmarked[$post_id] ?? false
    );
}

JSONResponse::success([
    'posts' => $post_payloads,
    'hasMore' => $has_more,
]) -> send();
