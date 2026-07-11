<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

JSONResponse::success(['token' => WSToken::issue((int) Auth::id())]) -> send();
