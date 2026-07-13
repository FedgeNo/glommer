<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

// Each upload decodes and resizes an attacker-controlled image (real CPU/
// memory work) - cap it so the endpoint can't be used for resource
// exhaustion, same as the other resource-intensive endpoints.
$rate_key = 'upload-avatar:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 15, 3600)) {
    JSONResponse::error('Too many avatar changes in a short time. Please try again later.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

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

$thumbnail_path = $avatar_dir . '/' . $current_user -> userId . '-thumb.jpg';

// Only the thumbnail is ever actually served - User::avatarPath() (the one
// place an avatar URL is built anywhere in the app) always points at
// -thumb.jpg, so a full-size copy would just be wasted CPU/disk on every
// upload.
$thumbnail_ok = ImageProcessor::resizeAndSave($image, $thumbnail_path, ImageProcessor::THUMBNAIL_MAX_DIMENSION);

imagedestroy($image);

if (!$thumbnail_ok) {
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
