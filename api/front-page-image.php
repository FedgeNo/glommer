<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check() || Auth::id() !== 1) {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}

if (!isset($_FILES['frontPageImage'])) {
    JSONResponse::fieldError('frontPageImage', JSONResponse::localized('chooseFileFirst')) -> send();
}

if ($_FILES['frontPageImage']['error'] !== UPLOAD_ERR_OK) {
    JSONResponse::fieldError('frontPageImage', JSONResponse::localized('uploadMissing')) -> send();
}

if (!FrontPageImage::updateFromUpload($_FILES['frontPageImage']['tmp_name'])) {
    JSONResponse::fieldError('frontPageImage', JSONResponse::localized('imageUnreadable')) -> send();
}

JSONResponse::success(['saved' => true, 'url' => FrontPageImage::URL()]) -> send();
