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
$offset = max(0, (int) ($payload['offset'] ?? 0));
$has_more = false;

if ($query === '') {
    // The empty-query suggestion list isn't paginated - it's a fixed, ranked
    // set (mutual-friend count, falling back to random), not the query the
    // offset below walks.
    $candidates = new EligibleSuggestedUserList((int) $current_user -> userId) -> rows();
} else {
    // Escape LIKE wildcards so a literal % or _ in the query doesn't match everything.
    $like = '%' . addcslashes($query, '\\%_') . '%';

    // The bio is searched full-text (whole-word / prefix), the username and
    // display name by substring as before. Each query word must prefix-match a
    // bio word (+word*); a query that's only punctuation leaves this empty, so
    // just the name LIKEs run.
    $ft_tokens = array_filter(preg_split('/[^A-Za-z0-9_]+/', $query));
    $ft_query = implode(' ', array_map(static fn (string $token): string => '+' . $token . '*', $ft_tokens));

    $not_banned = 0;
    $limit = 20;
    $fetch_limit = $limit + 1;

    // nameMatch (a hit on the username or display name) orders every name match
    // ahead of a bio-only (full-text) match. The ordering is stable for a given
    // query, so infinite scroll just re-runs the same query at a growing offset.
    $candidates = DB::rows('
SELECT *, (`slug` LIKE ? OR `title` LIKE ?) AS `nameMatch`
    FROM `Users`
    WHERE (`slug` LIKE ? OR `title` LIKE ? OR MATCH(`description`) AGAINST(? IN BOOLEAN MODE))
        AND `userId` != ? AND `banned` = ?
    ORDER BY `nameMatch` DESC
    LIMIT ? OFFSET ?
', 'User', 'sssssiiii', $like, $like, $like, $like, $ft_query, $current_user -> userId, $not_banned, $fetch_limit, $offset);

    $has_more = count($candidates) > $limit;

    if ($has_more) {
        array_pop($candidates);
    }
}

$users = [];

foreach ($candidates as $candidate) {
    $users[] = OtherUser::payloadFor($candidate, $current_user);
}

JSONResponse::success([
    'users' => $users,
    'hasMore' => $has_more,
]) -> send();
