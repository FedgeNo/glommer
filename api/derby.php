<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') JSONResponse::localizedError('methodNotAllowed', 405) -> send();
$payload = APIRequest::read(['action' => 'text', 'requestKey' => 'text', 'mode' => 'text', 'vehicle' => 'text', 'upgrade' => 'text', 'level' => 'integer', 'result' => 'json']);
try {
    $action = $payload['action'] ?? 'state';
    if (!in_array($action, ['state', 'race', 'upgrade', 'reward'], true)) throw new \InvalidArgumentException('Unknown game action.');
    $key = $payload['requestKey'] ?? '';
    if ($action !== 'state' && preg_match('/^[a-f0-9]{32}$/D', $key) !== 1) throw new \InvalidArgumentException('Invalid transaction identifier.');
    if ($action === 'reward' && !is_array($payload['result'] ?? null)) throw new \InvalidArgumentException('Missing race result.');
    $wallet = GameWallet::current();
    $response = match ($action) {
        'race' => Derby::enter($wallet, $key, $payload['mode'] ?? ''),
        'upgrade' => Derby::purchase($wallet, $key, $payload['vehicle'] ?? '', $payload['upgrade'] ?? '', $payload['level'] ?? -1),
        'reward' => Derby::finish($wallet, $key, $payload['result']),
        default => ['wallet' => GameWallet::visit($wallet)],
    };
    $response['upgrades'] = Derby::career($wallet);
    JSONResponse::success($response) -> send();
} catch (\InvalidArgumentException $exception) {
    JSONResponse::error($exception -> getMessage(), 422) -> send();
} catch (\DomainException $exception) {
    JSONResponse::error($exception -> getMessage(), 409) -> send();
}
