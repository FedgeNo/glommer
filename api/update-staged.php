<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

$payload = json_decode((string) file_get_contents('php://input'), true);

if (!is_array($payload)) {
    JSONResponse::error('Malformed request', 422) -> send();
}

$staged = StagedPost::load((int) ($payload['stagedPostId'] ?? 0));

if ($staged === null || (int) $staged -> userId !== Auth::id()) {
    JSONResponse::error('Not found', 404) -> send();
}

// The same field rules api/stage-post.php applies when the row is born.
$title = ControlCharacters::strip(mb_substr(trim((string) ($payload['title'] ?? '')), 0, 255));
$description_raw = (string) ($payload['description'] ?? '');
$link_url = trim((string) ($payload['linkURL'] ?? ''));

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

StagedPost::update(
    (int) $staged -> stagedPostId,
    (int) Auth::id(),
    $title_value,
    $description_value,
    $description_delta_value,
    $link_url_value,
    $latitude,
    $longitude,
    $publish_at_value
);

$updated = StagedPost::load((int) $staged -> stagedPostId);

JSONResponse::success([
    'stagedPostId' => (int) $updated -> stagedPostId,
    'title' => $updated -> title,
    'description' => $updated -> description,
    'descriptionDelta' => $updated -> descriptionDelta,
    'linkURL' => $updated -> linkURL,
    'latitude' => $updated -> latitude,
    'longitude' => $updated -> longitude,
    'publishAt' => $updated -> publishAt,
    'publishAtEpoch' => $updated -> publishAt !== null ? strtotime($updated -> publishAt) : null,
]) -> send();
