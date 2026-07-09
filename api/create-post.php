<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255);
$description = (string) ($_POST['description'] ?? '');
$link_url = trim((string) ($_POST['linkURL'] ?? ''));
$parent_id = isset($_POST['parentId']) && $_POST['parentId'] !== '' ? (int) $_POST['parentId'] : null;

$link_image_seed = trim((string) ($_POST['linkImageSeed'] ?? ''));

if (!preg_match('/^lp-[a-f0-9]{32}$/', $link_image_seed) || !UploadProcessor::exists($link_image_seed, 'ImageItem')) {
    $link_image_seed = '';
}

if (strlen($description) > 65535) {
    JSONResponse::error('Post text is too long', 422) -> send();
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
}

$uploaded_files = $_FILES['files'] ?? null;
$has_files = $uploaded_files !== null && count(array_filter($uploaded_files['error'], fn ($error) => $error === UPLOAD_ERR_OK)) > 0;
$has_text = trim(strip_tags($description)) !== '' || $link_url !== '';

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
$description_value = $description !== '' ? $description : null;
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

$needs_async = count(array_filter($valid_files, fn ($file) => $file['type'] !== 'image')) > 0;

if ($needs_async) {
    $batch_id = UploadBatch::stage($current_user -> userId, $parent_id, $title_value, $description_value, $link_url_value, $valid_files);

    $worker = escapeshellarg(__DIR__ . '/../bin/process-upload.php');
    exec('setsid php ' . $worker . ' ' . escapeshellarg($batch_id) . ' > /dev/null 2>&1 &');

    JSONResponse::success(['processing' => true]) -> send();
}

$stmt = mysqli_prepare($mysqli, '
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `linkURL`)
    VALUES (?, ?, ?, ?, ?)
');
mysqli_stmt_bind_param($stmt, 'iisss', $current_user -> userId, $parent_id, $title_value, $description_value, $link_url_value);
mysqli_stmt_execute($stmt);
$post_id = (int) mysqli_insert_id($mysqli);

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
$post -> linkURL = $link_url_value;
$post -> createdAt = date('Y-m-d H:i:s');
$post -> items = $items;
$post -> author = $current_user;

JSONResponse::success($post -> toPayload(0, 0, false)) -> send();
