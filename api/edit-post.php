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
$mysqli = Database::connection();

// api_post() (main.js) sends a JSON body, not form-encoded - $_POST is
// empty for this request, same as api/delete.php.
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$post_id = (int) ($payload['postId'] ?? 0);

$owner_stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($owner_stmt, 'i', $post_id);
mysqli_stmt_execute($owner_stmt);
$owner_result = mysqli_stmt_get_result($owner_stmt);
$owner_row = mysqli_fetch_assoc($owner_result);

if ($owner_row === null || (int) $owner_row['userId'] !== $current_user -> userId) {
    JSONResponse::error('Not your post', 403) -> send();
}

// Editing is text/title/link only - attached media (images/video/audio)
// can't be added or removed here (see the class docblock on why: it would
// touch the async upload-processing pipeline, FeedItems, and hashtag
// re-indexing all at once, well beyond "fix a typo"). A post that has no
// text/title/link at all (a pure media post) has nothing this endpoint can
// change, but that's caught below by the same "no content" rule create-post
// already enforces - a media-only post still needs SOME of title/link/body
// to remain non-empty after the edit, same as at creation.
$title = mb_substr(trim((string) ($payload['title'] ?? '')), 0, 255);
$description_raw = (string) ($payload['description'] ?? '');
$link_url = trim((string) ($payload['linkURL'] ?? ''));

// Mirrors create-post.php's Delta validation exactly - same limits, same
// sanitize/plaintext derivation, so an edited post is held to the identical
// rules a new one is.
$description_value = null;
$description_delta_value = null;
$description_ops = [];

if ($description_raw !== '') {
    if (strlen($description_raw) > 262144) {
        JSONResponse::error('Post text is too long', 422) -> send();
    }

    if (!is_array(json_decode($description_raw, true))) {
        JSONResponse::error('Your editor is out of date. Please refresh the page and try again.', 426) -> send();
    }

    $description_ops = Delta::sanitize(Delta::decode($description_raw));
    $description_plaintext = Delta::plainText($description_ops);

    if (strlen($description_plaintext) > 65535) {
        JSONResponse::error('Post text is too long', 422) -> send();
    }

    if ($description_plaintext !== '') {
        $description_value = $description_plaintext;
        $description_delta_value = json_encode(['ops' => $description_ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if ($link_url !== '') {
    if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $link_url)) {
        $link_url = 'https://' . $link_url;
    }

    if (!preg_match('/^https?:\/\//i', $link_url)) {
        JSONResponse::error('Link URL must be an http:// or https:// link', 422) -> send();
    }

    if (strlen($link_url) > 255) {
        JSONResponse::error('Link URL is too long', 422) -> send();
    }

    if (!URL::isPublicHTTP($link_url)) {
        JSONResponse::error('That link points to a local or private address and can\'t be posted.', 422) -> send();
    }
}

$title_value = $title !== '' ? $title : null;
$link_url_value = $link_url !== '' ? $link_url : null;

$media_count_stmt = mysqli_prepare($mysqli, '
SELECT COUNT(*) AS `count`
    FROM `FeedItems`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($media_count_stmt, 'i', $post_id);
mysqli_stmt_execute($media_count_stmt);
$media_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($media_count_stmt))['count'];

// A post that had attached media keeps it regardless of the text edit (media
// isn't editable here), so "no content" only means no title/link/body AND
// no pre-existing media to fall back on.
if ($title_value === null && $link_url_value === null && $description_value === null && $media_count === 0) {
    JSONResponse::error('Post has no content', 422) -> send();
}

// Same "media post XOR link post" rule create-post enforces. A post with
// attached media never had a link to begin with (creation already enforces
// the XOR), so this only ever fires when the edit is trying to newly add
// one to a media post - editing an existing link-post's URL always passes,
// since it has no media by construction.
if ($link_url_value !== null && $media_count > 0) {
    JSONResponse::error('A post can have either attached files or a link, not both', 422) -> send();
}

$edited_at = date('Y-m-d H:i:s');

$update_stmt = mysqli_prepare($mysqli, '
UPDATE `Posts`
    SET `title` = ?, `description` = ?, `descriptionDelta` = ?, `linkURL` = ?, `editedAt` = ?
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($update_stmt, 'sssssi', $title_value, $description_value, $description_delta_value, $link_url_value, $edited_at, $post_id);
mysqli_stmt_execute($update_stmt);

Hashtag::reindexPost($post_id, $description_ops);
Mention::notify(Mention::reindexPost($post_id, $description_ops), $current_user -> userId, $post_id);

// Re-fetch rather than hand-assemble the row: createdAt, parentId, and
// keywords (just rewritten by reindexPost()) all need to reflect the true
// current DB state, not values this script would otherwise have to
// duplicate/guess at.
$updated_stmt = mysqli_prepare($mysqli, '
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($updated_stmt, 'i', $post_id);
mysqli_stmt_execute($updated_stmt);
$post = Post::fromRowWithItems(mysqli_fetch_assoc(mysqli_stmt_get_result($updated_stmt)));
$post -> author = $current_user;

$reply_count_stmt = mysqli_prepare($mysqli, '
SELECT COUNT(*) AS `count`
    FROM `Posts`
    WHERE `parentId` = ?
');
mysqli_stmt_bind_param($reply_count_stmt, 'i', $post_id);
mysqli_stmt_execute($reply_count_stmt);
$reply_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($reply_count_stmt))['count'];

$like_count_stmt = mysqli_prepare($mysqli, '
SELECT COUNT(*) AS `count`
    FROM `Likes`
    WHERE `postId` = ?
');
mysqli_stmt_bind_param($like_count_stmt, 'i', $post_id);
mysqli_stmt_execute($like_count_stmt);
$like_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($like_count_stmt))['count'];

$liked_stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Likes`
    WHERE `postId` = ? AND `userId` = ?
');
mysqli_stmt_bind_param($liked_stmt, 'ii', $post_id, $current_user -> userId);
mysqli_stmt_execute($liked_stmt);
mysqli_stmt_store_result($liked_stmt);
$liked = mysqli_stmt_num_rows($liked_stmt) > 0;

$bookmarked_stmt = mysqli_prepare($mysqli, '
SELECT 1
    FROM `Bookmarks`
    WHERE `postId` = ? AND `userId` = ?
');
mysqli_stmt_bind_param($bookmarked_stmt, 'ii', $post_id, $current_user -> userId);
mysqli_stmt_execute($bookmarked_stmt);
mysqli_stmt_store_result($bookmarked_stmt);
$bookmarked = mysqli_stmt_num_rows($bookmarked_stmt) > 0;

JSONResponse::success($post -> toPayload($reply_count, $like_count, $liked, $bookmarked)) -> send();
