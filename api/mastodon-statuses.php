<?php

declare(strict_types=1);

const IS_API_REQUEST = true;
const IS_STATELESS_REQUEST = true;
const IS_MASTODON_POST_REQUEST = true;

require __DIR__ . '/../src/init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

$current_user = APIToken::userForBearer((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));

if ($current_user === null) {
    header('WWW-Authenticate: Bearer realm="Glommer"');
    JSONResponse::error('Invalid access token', 401) -> send();
}

$content_type = strtolower(trim(explode(';', (string) ($_SERVER['CONTENT_TYPE'] ?? ''), 2)[0]));
$input = $_POST;

if ($content_type === 'application/json') {
    $input = APIRequest::read([
        'status' => 'text',
        'visibility' => 'text',
        'spoiler_text' => 'text',
        'in_reply_to_id' => 'integer',
    ]);
}

if (!is_array($input) || !is_string($input['status'] ?? null)) {
    JSONResponse::error('A text status is required', 422) -> send();
}

if (($input['visibility'] ?? 'public') !== 'public') {
    JSONResponse::error('Only public statuses are supported', 422) -> send();
}

foreach (['media_ids', 'poll', 'scheduled_at', 'quoted_status_id'] as $unsupported) {
    if (isset($input[$unsupported])) {
        JSONResponse::error('Unsupported status field: ' . $unsupported, 422) -> send();
    }
}

if ($_FILES !== []) {
    JSONResponse::error('Media uploads are not supported', 422) -> send();
}

$status = trim($input['status']);

if ($status === '' || strlen($status) > 65535) {
    JSONResponse::error('Status text is empty or too long', 422) -> send();
}

$sensitive_raw = $input['sensitive'] ?? false;

if (!in_array($sensitive_raw, [false, true, 'false', 'true', '0', '1', 0, 1], true)) {
    JSONResponse::error('Invalid sensitive value', 422) -> send();
}

$sensitive = in_array($sensitive_raw, [true, 'true', '1', 1], true);
$spoiler_text = $input['spoiler_text'] ?? '';

if (!is_string($spoiler_text)) {
    JSONResponse::error('Invalid spoiler text', 422) -> send();
}

$parent_id = $input['in_reply_to_id'] ?? null;

if ($parent_id !== null && (!is_scalar($parent_id) || !ctype_digit((string) $parent_id))) {
    JSONResponse::error('Invalid reply ID', 422) -> send();
}

$_POST = [
    'description' => json_encode(['ops' => [['insert' => $status . "\n"]]], JSON_THROW_ON_ERROR),
    'sensitive' => $sensitive ? '1' : '0',
    'contentWarning' => $spoiler_text,
];

if ($parent_id !== null) {
    $_POST['parentId'] = (string) $parent_id;
}

require __DIR__ . '/../src/post-create.php';
