<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') JSONResponse::localizedError('methodNotAllowed', 405) -> send();
if (!Auth::check()) JSONResponse::localizedError('notLoggedIn', 401) -> send();
$payload = APIRequest::read(['action' => 'text', 'move' => 'text', 'amount' => 'integer', 'tableId' => 'integer', 'version' => 'integer', 'body' => 'text', 'requestKey' => 'text']);
try {
    JSONResponse::success(Poker::request((int) Auth::id(), $payload['action'] ?? 'state', $payload)) -> send();
} catch (\InvalidArgumentException $exception) {
    JSONResponse::error($exception -> getMessage(), 422) -> send();
} catch (\DomainException $exception) {
    JSONResponse::error($exception -> getMessage(), 409) -> send();
}
