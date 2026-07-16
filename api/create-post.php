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
$mysqli = DB::connection();

// If the whole request body exceeded post_max_size, PHP has already thrown away
// $_POST and $_FILES before this script ran. Catch that here so an oversized
// upload gets a clear "too large" error instead of a misleading "no content" one.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === []) {
    JSONResponse::error('Your upload is too large. The maximum total upload size is ' . ini_get('post_max_size') . 'B.', 413) -> send();
}

$title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
$description_raw = (string) ($_POST['description'] ?? '');
$link_url = trim((string) ($_POST['linkURL'] ?? ''));
$parent_id = isset($_POST['parentId']) && $_POST['parentId'] !== '' ? (int) $_POST['parentId'] : null;

$link_image_seed = trim((string) ($_POST['linkImageSeed'] ?? ''));

if (!preg_match('/^lp-[a-f0-9]{32}$/', $link_image_seed) || !UploadProcessor::exists($link_image_seed, 'ImageItem')) {
    $link_image_seed = '';
}

// The composer submits the Quill Delta as JSON. Cap the raw input first (bounds
// the decode work), reject a stale client that still POSTs rendered HTML, then
// reduce to the ops we render and derive the plaintext the `description` column
// (and search/meta/RSS) uses. $description_value / $description_delta_value stay
// null for a blank body, so both columns agree there's no rich content.
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

$uploaded_files = $_FILES['files'] ?? null;

// Surface upload failures (most commonly a single file over upload_max_filesize)
// rather than silently skipping the file - otherwise a too-large media upload
// with accompanying text quietly becomes a text-only post the user didn't intend.
// UPLOAD_ERR_NO_FILE is the exception: an empty slot in the files[] array just
// means nothing was attached there, which is fine.
if ($uploaded_files !== null) {
    foreach ($uploaded_files['error'] as $error) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            JSONResponse::error('One of your files is larger than the ' . ini_get('upload_max_filesize') . 'B upload limit.', 413) -> send();
        }

        if ($error !== UPLOAD_ERR_OK && $error !== UPLOAD_ERR_NO_FILE) {
            JSONResponse::error('One of your files failed to upload. Please try again.', 400) -> send();
        }
    }
}

// Refuse uploads outright when the disk is nearly full - the database (on the
// same host) needs the remaining headroom far more than the feed needs
// another upload.
if ($uploaded_files !== null && !UploadProcessor::hasFreeDiskSpace((int) array_sum($uploaded_files['size']))) {
    JSONResponse::error('Uploads are temporarily unavailable - the server is low on storage. Please try again later.', 507) -> send();
}

$has_files = $uploaded_files !== null && count(array_filter($uploaded_files['error'], fn ($error) => $error === UPLOAD_ERR_OK)) > 0;
$has_text = $description_value !== null || $link_url !== '';

// A post is either a media post or a link post, never both. The composer
// enforces this in the UI (each field hides when the other is used), but the
// rule has to hold here too or a direct API call could create a combined
// post - which the renderers deliberately have no layout for.
if ($has_files && $link_url !== '') {
    JSONResponse::error('A post can have either attached files or a link, not both', 422) -> send();
}

// The staged image is the link's preview thumbnail, not standalone media -
// with no link on the post it has nothing to belong to, so discard it.
if ($link_image_seed !== '' && $link_url === '') {
    UploadProcessor::delete($link_image_seed, 'ImageItem', null);
    $link_image_seed = '';
}

if (!$has_text && !$has_files) {
    JSONResponse::error('Post has no content', 422) -> send();
}

if ($parent_id !== null) {
    $parent_stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
');
    mysqli_stmt_bind_param($parent_stmt, 'i', $parent_id);
    mysqli_stmt_execute($parent_stmt);
    $parent_result = mysqli_stmt_get_result($parent_stmt);
    $parent_row = mysqli_fetch_assoc($parent_result);

    if ($parent_row === null) {
        JSONResponse::error('Post not found', 404) -> send();
    }

    if (Block::exists($current_user -> userId, (int) $parent_row['userId'])) {
        JSONResponse::error('Unable to reply to this post', 403) -> send();
    }
}

$title_value = $title !== '' ? $title : null;
$link_url_value = $link_url !== '' ? $link_url : null;

$valid_files = [];

if ($has_files) {
    $file_count = count($uploaded_files['name']);

    for ($i = 0; $i < $file_count; $i++) {
        if ($uploaded_files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $type = UploadProcessor::classify($uploaded_files['tmp_name'][$i]);

        if ($type === null) {
            continue;
        }

        $valid_files[] = [
            'tmpPath' => $uploaded_files['tmp_name'][$i],
            'originalFilename' => $uploaded_files['name'][$i],
            'type' => $type,
        ];
    }
}

// The earlier guard keyed off the raw upload count; re-check now that files
// have been classified, so an upload that produced no valid media (e.g. a
// plain-text file that classify() rejected) can't slip through as a
// completely contentless post.
if (!$has_text && $valid_files === []) {
    JSONResponse::error('Post has no content', 422) -> send();
}

$needs_async = count(array_filter($valid_files, fn ($file) => $file['type'] !== 'image')) > 0;

if ($needs_async) {
    // Each of these spawns its own detached ffmpeg transcode - without a cap,
    // one user firing off many video/audio posts in a burst could exhaust
    // CPU/memory with unbounded concurrent worker processes.
    $async_upload_rate_key = 'async-upload:' . $current_user -> userId;

    if (RateLimiter::tooManyAttempts($async_upload_rate_key, 5, 600)) {
        JSONResponse::error('Too many video/audio uploads in a short time. Please wait a bit and try again.', 429) -> send();
    }

    RateLimiter::recordAttempt($async_upload_rate_key);

    // Stage the batch and return immediately. The upload-worker service
    // (bin/upload-worker.php) drains the queue at a bounded concurrency - no
    // per-upload worker is spawned here any more, which is exactly what let a
    // burst of uploads run unlimited concurrent transcodes and overwhelm the
    // host. Completion is signalled by the postReady/uploadPartlyFailed/
    // uploadFailed notification the worker creates when it finishes.
    UploadBatch::stage($current_user -> userId, $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value, $valid_files);

    JSONResponse::success(['processing' => true]) -> send();
}

$stmt = mysqli_prepare($mysqli, '
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `descriptionDelta`, `linkURL`)
    VALUES (?, ?, ?, ?, ?, ?)
');
mysqli_stmt_bind_param($stmt, 'iissss', $current_user -> userId, $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value);
mysqli_stmt_execute($stmt);
$post_id = (int) mysqli_insert_id($mysqli);

Hashtag::indexPost($post_id, $description_ops);
Mention::notify(Mention::indexPost($post_id, $description_ops), $current_user -> userId, $post_id);

if ($parent_id !== null) {
    Notification::create((int) $parent_row['userId'], $current_user -> userId, 'reply', $parent_id);
} else {
    Timeline::fanOutPost($current_user -> userId, $post_id);
}

$items = [];

foreach ($valid_files as $file) {
    // Insert a placeholder row first so we have a real, numbered itemId to
    // name the processed files after (no user-controlled filenames survive).
    $placeholder_item_type = 'ImageItem';
    $placeholder_stmt = mysqli_prepare($mysqli, '
INSERT INTO `FeedItems` (`postId`, `itemType`)
    VALUES (?, ?)
');
    mysqli_stmt_bind_param($placeholder_stmt, 'is', $post_id, $placeholder_item_type);
    mysqli_stmt_execute($placeholder_stmt);
    $item_id = (int) mysqli_insert_id($mysqli);

    $result = UploadProcessor::process($file['tmpPath'], $item_id, $file['originalFilename']);

    if ($result === null) {
        $delete_stmt = mysqli_prepare($mysqli, '
DELETE
    FROM `FeedItems`
    WHERE `itemId` = ?
');
        mysqli_stmt_bind_param($delete_stmt, 'i', $item_id);
        mysqli_stmt_execute($delete_stmt);
        continue;
    }

    $update_stmt = mysqli_prepare($mysqli, '
UPDATE `FeedItems`
    SET `itemType` = ?
    WHERE `itemId` = ?
');
    mysqli_stmt_bind_param($update_stmt, 'si', $result['itemType'], $item_id);
    mysqli_stmt_execute($update_stmt);

    $items[] = FeedItem::fromRow(['itemId' => $item_id, 'postId' => $post_id, 'itemType' => $result['itemType']]);
}

if ($link_image_seed !== '') {
    $link_image_item_type = 'ImageItem';
    $link_image_placeholder_stmt = mysqli_prepare($mysqli, '
INSERT INTO `FeedItems` (`postId`, `itemType`)
    VALUES (?, ?)
');
    mysqli_stmt_bind_param($link_image_placeholder_stmt, 'is', $post_id, $link_image_item_type);
    mysqli_stmt_execute($link_image_placeholder_stmt);
    $link_image_item_id = (int) mysqli_insert_id($mysqli);

    UploadProcessor::rename($link_image_seed, $link_image_item_id, 'ImageItem', null);

    $items[] = FeedItem::fromRow(['itemId' => $link_image_item_id, 'postId' => $post_id, 'itemType' => 'ImageItem']);
}

$post = new Post();
$post -> postId = $post_id;
$post -> userId = (int) $current_user -> userId;
$post -> parentId = $parent_id;
$post -> title = $title_value;
$post -> description = $description_value;
$post -> descriptionDelta = $description_delta_value;
$post -> linkURL = $link_url_value;
$post -> createdAt = date('Y-m-d H:i:s');
$post -> items = $items;
$post -> author = $current_user;

JSONResponse::success($post -> toPayload(0, 0, false, false)) -> send();
