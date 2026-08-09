<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$current_user = Auth::user();
$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    JSONResponse::error('Malformed request', 422) -> send();
}

// A hoard of staged posts is a hoard of future publishes; fifty is a queue,
// five hundred is a bot.
$existing = DB::row('
SELECT COUNT(*) AS `total`
    FROM `StagedPosts`
    WHERE `userId` = ?
', 'PostCountData', 'i', (int) $current_user -> userId);

if ((int) $existing -> total >= 50) {
    JSONResponse::error('You already have 50 drafts and scheduled posts - publish or discard some first.', 422) -> send();
}

// Mirrors api/create-post.php's rules for the fields a staged post can carry.
// No files and no poll here: media publishes immediately (the staging queue
// on disk is not this table), and a poll's clock starts when readers can
// vote, which is publish time - scheduling one is a contradiction.
$title = ControlCharacters::strip(mb_substr(trim((string) ($payload['title'] ?? '')), 0, 255));
$description_raw = (string) ($payload['description'] ?? '');
$link_url = trim((string) ($payload['linkURL'] ?? ''));
$sensitive = ($payload['sensitive'] ?? false) === true ? 1 : 0;

$description_value = null;
$description_delta_value = null;

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

$link_url_value = null;

if ($link_url !== '') {
    if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $link_url)) {
        $link_url = 'https://' . $link_url;
    }

    if (!preg_match('/^https?:\/\//i', $link_url) || strlen($link_url) > 255 || !URL::isValidHTTPURL($link_url)) {
        JSONResponse::fieldError('linkURL', 'Point this at a real domain name.') -> send();
    }

    $link_url_value = $link_url;
}

$title_value = $title !== '' ? $title : null;

if ($title_value === null && $description_value === null && $link_url_value === null) {
    JSONResponse::error('Post has no content', 422) -> send();
}

$latitude = isset($payload['latitude']) && $payload['latitude'] !== '' ? (float) $payload['latitude'] : null;
$longitude = isset($payload['longitude']) && $payload['longitude'] !== '' ? (float) $payload['longitude'] : null;

if (($latitude === null) !== ($longitude === null)
    || ($latitude !== null && (abs($latitude) > 90 || abs($longitude) > 180))) {
    JSONResponse::error('Malformed location', 422) -> send();
}

// The clock, when there is one: seconds since the epoch, converted here to
// the server's own datetime - the client's wall-clock text would smuggle in
// its time zone.
$publish_at_value = null;

if (isset($payload['publishAtEpoch']) && $payload['publishAtEpoch'] !== '' && $payload['publishAtEpoch'] !== null) {
    $epoch = (int) $payload['publishAtEpoch'];

    if ($epoch <= time() + 60) {
        JSONResponse::error('The publish time has to be in the future.', 422) -> send();
    }

    if ($epoch > time() + StagedPost::MAX_DAYS_AHEAD * 86400) {
        JSONResponse::error('Posts can be scheduled at most ' . StagedPost::MAX_DAYS_AHEAD . ' days ahead.', 422) -> send();
    }

    $publish_at_value = date('Y-m-d H:i:s', $epoch);
}

$staged_post_id = StagedPost::stage(
    (int) $current_user -> userId,
    $title_value,
    $description_value,
    $description_delta_value,
    $link_url_value,
    $latitude,
    $longitude,
    $sensitive,
    $publish_at_value
);

JSONResponse::success([
    'stagedPostId' => $staged_post_id,
    'publishAt' => $publish_at_value,
]) -> send();
