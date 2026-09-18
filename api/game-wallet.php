<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') JSONResponse::localizedError('methodNotAllowed', 405) -> send();
APIRequest::read([]);
try {
    JSONResponse::success(GameWallet::visit(GameWallet::current())) -> send();
} catch (\DomainException $exception) {
    JSONResponse::error($exception -> getMessage(), 409) -> send();
}
