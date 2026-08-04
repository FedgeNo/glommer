<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$public_key = is_array($payload['publicKey'] ?? null) ? $payload['publicKey'] : [];
$wrapped = is_array($payload['wrappedPrivateKey'] ?? null) ? $payload['wrappedPrivateKey'] : [];

// Replacing the keys decides who can read every message sent to this account
// from now on, so it takes the account password - a live session alone isn't
// enough, same bar as changing the password itself. Rate-limited under the
// shared password-verify key so this endpoint can't be used to guess the
// password any faster than the others that confirm it.
$password_rate_key = 'password-verify:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($password_rate_key, 10, 600)) {
    JSONResponse::error('Too many attempts. Please try again later.', 429) -> send();
}

if (!$current_user -> verifyPassword((string) ($payload['password'] ?? ''))) {
    RateLimiter::recordAttempt($password_rate_key);
    JSONResponse::error('That isn\'t your account password.', 403) -> send();
}

// Both blobs are rebuilt from allowlisted fields rather than stored as
// received, so nothing else ever rides into the row. The public key is a
// P-256 JWK; its coordinates are 32 bytes each, 43 characters in unpadded
// base64url. The one thing that must never appear is 'd' - the private
// scalar - which the allowlist excludes by construction.
$coordinate_pattern = '/\A[A-Za-z0-9_-]{43}\z/';

if (($public_key['kty'] ?? null) !== 'EC' || ($public_key['crv'] ?? null) !== 'P-256'
    || preg_match($coordinate_pattern, (string) ($public_key['x'] ?? '')) !== 1
    || preg_match($coordinate_pattern, (string) ($public_key['y'] ?? '')) !== 1) {
    JSONResponse::error('Malformed public key.', 422) -> send();
}

$stored_public_key = json_encode([
    'kty' => 'EC',
    'crv' => 'P-256',
    'x' => $public_key['x'],
    'y' => $public_key['y'],
]);

// The wrapped private key is opaque ciphertext to this server - only its
// shape is checked: PBKDF2 salt, an iteration count that is an actual cost,
// a GCM nonce, and the encrypted key itself. A low iteration count would
// quietly turn "wrapped under a passphrase" into "barely wrapped at all".
$salt = base64_decode((string) ($wrapped['salt'] ?? ''), true);
$iterations = (int) ($wrapped['iterations'] ?? 0);
$iv = base64_decode((string) ($wrapped['iv'] ?? ''), true);
$ciphertext = base64_decode((string) ($wrapped['ciphertext'] ?? ''), true);

if ($salt === false || strlen($salt) !== 16
    || $iterations < 100000 || $iterations > 10000000
    || $iv === false || strlen($iv) !== 12
    || $ciphertext === false || strlen($ciphertext) < 17 || strlen($ciphertext) > 4096) {
    JSONResponse::error('Malformed key backup.', 422) -> send();
}

$stored_wrapped_key = json_encode([
    'salt' => base64_encode($salt),
    'iterations' => $iterations,
    'iv' => base64_encode($iv),
    'ciphertext' => base64_encode($ciphertext),
]);

DB::run('
UPDATE `Users`
    SET `messagePublicKey` = ?, `messageWrappedPrivateKey` = ?
    WHERE `userId` = ?
', 'ssi', $stored_public_key, $stored_wrapped_key, $current_user -> userId);

// Key changes are announced by mail, so a change the account holder didn't
// make doesn't stay invisible - whoever holds the new keys can read every
// message sent to this account from now on.
$site_title = (string) Config::get('siteTitle');
$notice = 'The message encryption keys for your ' . $site_title . ' account were just replaced. '
    . 'If this was you - turning encryption on, changing your passphrase, or resetting your keys - there is nothing to do. '
    . 'If it was not, change your password immediately: whoever replaced them can read messages sent to you from now on.';

Mailer::send(
    (string) $current_user -> email,
    $current_user -> title ?: $current_user -> slug,
    'Your message encryption keys changed',
    $notice,
    '<p>' . htmlspecialchars($notice) . '</p>'
);

JSONResponse::success(['saved' => true]) -> send();
