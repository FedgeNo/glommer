<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$seed = (string) ($payload['seed'] ?? '');

if (!preg_match('/^lp-[a-f0-9]{32}$/', $seed)) {
    JSONResponse::error('Invalid seed', 422) -> send();
}

UploadProcessor::delete($seed, 'ImageItem', null);

JSONResponse::success(['discarded' => true]) -> send();
