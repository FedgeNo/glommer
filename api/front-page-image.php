<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::error('Not authorized', 403) -> send();
}

if (!isset($_FILES['frontPageImage'])) {
    JSONResponse::error('No file was uploaded', 422) -> send();
}

if ($_FILES['frontPageImage']['error'] !== UPLOAD_ERR_OK) {
    JSONResponse::error('The image upload failed. Please try again.', 422) -> send();
}

if (!FrontPageImage::updateFromUpload($_FILES['frontPageImage']['tmp_name'])) {
    JSONResponse::error('That file could not be read as an image.', 422) -> send();
}

JSONResponse::success(['saved' => true, 'url' => FrontPageImage::URL()]) -> send();
