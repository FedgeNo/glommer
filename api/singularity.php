<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') JSONResponse::localizedError('methodNotAllowed', 405) -> send();
$payload = APIRequest::read(['requestKey' => 'text', 'stake' => 'integer']);
try {
    $stake = Singularity::validate($payload['stake'] ?? null);
    $key = $payload['requestKey'] ?? '';
    if (preg_match('/^[a-f0-9]{32}$/D', $key) !== 1) throw new \InvalidArgumentException('Invalid play identifier.');
    JSONResponse::success(Singularity::spin(GameWallet::current(), $key, $stake)) -> send();
} catch (\InvalidArgumentException $exception) {
    JSONResponse::error($exception -> getMessage(), 422) -> send();
} catch (\DomainException $exception) {
    JSONResponse::error($exception -> getMessage(), 409) -> send();
}
