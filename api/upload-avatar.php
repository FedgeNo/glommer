<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$uploaded_file = $_FILES['avatar'] ?? null;

if ($uploaded_file === null || $uploaded_file['error'] !== UPLOAD_ERR_OK) {
    JSONResponse::error('No file uploaded', 422) -> send();
}

// Refuse uploads outright when the disk is nearly full - the database (on the
// same host) needs the remaining headroom far more than a new avatar does.
if (!UploadProcessor::hasFreeDiskSpace((int) $uploaded_file['size'])) {
    JSONResponse::error('Uploads are temporarily unavailable - the server is low on storage. Please try again later.', 507) -> send();
}

$avatar_dir = dirname(__DIR__) . '/uploads/avatars';

if (!is_dir($avatar_dir)) {
    mkdir($avatar_dir, 0755, true);
}

$image = ImageProcessor::load($uploaded_file['tmp_name']);

if ($image === false) {
    JSONResponse::error('Not a valid image', 422) -> send();
}

$display_path = $avatar_dir . '/' . $current_user -> userId . '.jpg';
$thumbnail_path = $avatar_dir . '/' . $current_user -> userId . '-thumb.jpg';

$display_ok = ImageProcessor::resizeAndSave($image, $display_path, ImageProcessor::DISPLAY_MAX_DIMENSION);
$thumbnail_ok = ImageProcessor::resizeAndSave($image, $thumbnail_path, ImageProcessor::THUMBNAIL_MAX_DIMENSION);

imagedestroy($image);

if (!$display_ok || !$thumbnail_ok) {
    JSONResponse::error('Could not process image', 422) -> send();
}

$has_avatar = 1;

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `hasAvatar` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'ii', $has_avatar, $current_user -> userId);
mysqli_stmt_execute($stmt);

JSONResponse::success(['image' => ServerURL::absolute(User::avatarPath($current_user -> userId))]) -> send();
