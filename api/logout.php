<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

RememberToken::forget();
Auth::logout();

JSONResponse::success(['loggedOut' => true]) -> send();
