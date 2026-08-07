<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

if (!WebPushKeys::isConfigured()) {
    JSONResponse::error('Push is not configured on this server.', 503) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);

$endpoint = trim((string) ($payload['endpoint'] ?? ''));
$p256dh = trim((string) ($payload['p256dh'] ?? ''));
$auth = trim((string) ($payload['auth'] ?? ''));

// The endpoint is a URL the push service minted; the keys are what the
// browser generated for this subscription. Shapes checked here, cryptography
// checked by every send.
if (!str_starts_with($endpoint, 'https://') || strlen($endpoint) > 500
    || WebPushKeys::base64urlDecode($p256dh) === null || strlen((string) WebPushKeys::base64urlDecode($p256dh)) !== 65
    || WebPushKeys::base64urlDecode($auth) === null || strlen((string) WebPushKeys::base64urlDecode($auth)) !== 16) {
    JSONResponse::error('Malformed subscription', 422) -> send();
}

// A browser resubscribing (or a second member on a shared browser) replaces
// the endpoint's row - the push service treats the endpoint as one channel,
// and whoever subscribed last is who it belongs to.
DB::run('
INSERT INTO `PushSubscriptions` (`userId`, `endpoint`, `p256dh`, `auth`)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `userId` = VALUES(`userId`), `p256dh` = VALUES(`p256dh`), `auth` = VALUES(`auth`)
', 'isss', (int) Auth::id(), $endpoint, $p256dh, $auth);

JSONResponse::success(['subscribed' => true]) -> send();
