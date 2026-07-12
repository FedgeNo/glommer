<?php

declare(strict_types=1);

require __DIR__ . '/../src/init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();
$mysqli = Database::connection();

$payload = json_decode((string) file_get_contents('php://input'), true);
$new_email = trim((string) ($payload['newEmail'] ?? ''));
$current_password = (string) ($payload['currentPassword'] ?? '');

if ($current_user -> passwordHash === null || !password_verify($current_password, $current_user -> passwordHash)) {
    JSONResponse::error('Current password is incorrect', 422) -> send();
}

if ($new_email === '' || filter_var($new_email, FILTER_VALIDATE_EMAIL) === false) {
    JSONResponse::error('A valid email address is required', 422) -> send();
}

if (strcasecmp($new_email, (string) $current_user -> email) === 0) {
    JSONResponse::error('That is already your email address', 422) -> send();
}

// Each change sends a verification email - cap it so the endpoint can't be
// used to pump out mail.
$rate_key = 'change-email:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 5, 3600)) {
    JSONResponse::error('Too many email changes in a short time. Please try again later.', 429) -> send();
}

$taken_stmt = mysqli_prepare($mysqli, '
SELECT `userId`
    FROM `Users`
    WHERE `email` = ? AND `userId` != ?
');
mysqli_stmt_bind_param($taken_stmt, 'si', $new_email, $current_user -> userId);
mysqli_stmt_execute($taken_stmt);
mysqli_stmt_store_result($taken_stmt);

if (mysqli_stmt_num_rows($taken_stmt) > 0 || EmailChangeRevert::isReserved($new_email)) {
    JSONResponse::error('That email address is already in use', 422) -> send();
}

RateLimiter::recordAttempt($rate_key);

// Captured before the overwrite - EmailChangeRevert needs it to know what to
// revert back to, and it's the one place a "wasn't you?" notice can reach the
// real owner if the new address belongs to whoever just hijacked the account.
$previous_email = (string) $current_user -> email;

// The new address is unverified until its owner proves it - the account drops
// back behind the verification gate until then.
$unverified = 0;

$stmt = mysqli_prepare($mysqli, '
UPDATE `Users`
    SET `email` = ?, `verified` = ?
    WHERE `userId` = ?
');
mysqli_stmt_bind_param($stmt, 'sii', $new_email, $unverified, $current_user -> userId);
mysqli_stmt_execute($stmt);

Auth::clearUserCache();
$updated_user = Auth::user();

// Sends the verification link to the new address. If the mailer is down this
// verifies the user directly and notifies the admin instead (sendFor's own
// failure handling), so nobody gets stranded behind a gate no email can clear.
EmailVerification::sendFor($updated_user);

// Sends a "wasn't you?" notice to the OLD address with a revert link - the
// real owner may know nothing about this change if the new address is one an
// attacker (who already has the password) controls.
EmailChangeRevert::sendFor($updated_user, $previous_email);

JSONResponse::success(['changed' => true]) -> send();
