<?php

declare(strict_types=1);

/**
 * Internal-use endpoint for bin/install.php's live upload-limit check: reports
 * the values PHP actually resolves under the web SAPI, so the CLI installer -
 * which can't apply .user.ini itself, and can't even trust its own
 * user_ini.filename/user_ini.cache_ttl settings to reflect what the web SAPI
 * uses - can confirm the web server is really honoring .user.ini rather than
 * just trusting the file's contents. Deliberately DB-independent (must work
 * even pre-install, before .env exists) and exposes nothing sensitive -
 * upload limits aren't secret.
 */

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/src/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

JSONResponse::success([
    'uploadMaxFilesize' => ini_get('upload_max_filesize'),
    'postMaxSize' => ini_get('post_max_size'),
    'maxFileUploads' => ini_get('max_file_uploads'),
]) -> send();
