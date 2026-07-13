<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if (!Auth::check()) {
    JSONResponse::error('Not logged in', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$target_type = (string) ($payload['targetType'] ?? '');
$target_id = (int) ($payload['targetId'] ?? 0);
$reason = trim((string) ($payload['reason'] ?? ''));

$valid_types = ['post', 'user', 'message'];

if (!in_array($target_type, $valid_types, true) || $target_id === 0) {
    JSONResponse::error('Invalid report', 422) -> send();
}

if (strlen($reason) > 65535) {
    JSONResponse::error('Reason is too long', 422) -> send();
}

$target_user_id = Report::resolveTargetUserId($target_type, $target_id);

if ($target_user_id === null) {
    JSONResponse::error('Invalid report', 422) -> send();
}

// Reports about the admin - their account, their posts, their messages -
// are dead letters: only the admin and mods see reports, and the admin
// can't be banned, so nobody could ever act on one. Rejected here (not
// just hidden in the UI) so a hand-crafted request can't file one either.
if ($target_user_id === 1) {
    JSONResponse::error('This content can\'t be reported.', 422) -> send();
}

// A moderator already reviewed and dismissed a report on this content - it
// can't be reported again (posts/messages only; a user carries no such flag).
if (Report::isContentDismissed($target_type, $target_id)) {
    JSONResponse::error('This content has already been reviewed by a moderator.', 422) -> send();
}

$rate_key = 'report:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 20, 3600)) {
    JSONResponse::error('Too many reports. Please try again later.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

if (!Report::create($current_user -> userId, $target_type, $target_id, $reason !== '' ? $reason : null)) {
    JSONResponse::error('You\'ve already reported this.', 422) -> send();
}

JSONResponse::success(['reported' => true]) -> send();
