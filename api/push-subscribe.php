<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

Auth::requireLogin();

if (!WebPushKeys::isConfigured()) {
    JSONResponse::localizedError('pushIsNotConfiguredOnThisServer', 503) -> send();
}

$payload = APIRequest::read(['endpoint' => 'text', 'p256dh' => 'text', 'auth' => 'text']);

$payload = is_array($payload) ? $payload : [];
$endpoint = is_string($payload['endpoint'] ?? null) ? trim($payload['endpoint']) : '';
$p256dh = is_string($payload['p256dh'] ?? null) ? trim($payload['p256dh']) : '';
$auth = is_string($payload['auth'] ?? null) ? trim($payload['auth']) : '';

// The endpoint is a URL the push service minted; the keys are what the
// browser generated for this subscription. Shapes checked here, cryptography
// checked by every send.
if (!str_starts_with($endpoint, 'https://') || strlen($endpoint) > 500
    || WebPushKeys::base64urlDecode($p256dh) === null || strlen((string) WebPushKeys::base64urlDecode($p256dh)) !== 65
    || WebPushKeys::base64urlDecode($auth) === null || strlen((string) WebPushKeys::base64urlDecode($auth)) !== 16) {
    JSONResponse::localizedError('malformedSubscription', 422) -> send();
}

$status = PushSubscription::subscribe((int) Auth::id(), $endpoint, $p256dh, $auth, $_SERVER['HTTP_USER_AGENT'] ?? null);
if ($status === 'full') {
    JSONResponse::error((string) (Strings::for('PushNotificationSetting')['limits'] ?? ''), 409) -> send();
}
if ($status === 'limited') {
    header('Retry-After: 86400');
    JSONResponse::localizedError('tooManyRequestsPleaseTryAgainLater', 429) -> send();
}
if ($status !== 'subscribed') {
    JSONResponse::localizedError('notAuthorized', 403) -> send();
}
JSONResponse::success(PushSubscription::status((int) Auth::id(), $endpoint)) -> send();
