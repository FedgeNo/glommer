<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

if (!Auth::check()) {
    JSONResponse::localizedError('notLoggedIn', 401) -> send();
}

$current_user = Auth::user();

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];
$target_type = (string) ($payload['targetType'] ?? '');
$target_id = (int) ($payload['targetId'] ?? 0);
$reason = trim((string) ($payload['reason'] ?? ''));

$valid_types = ['post', 'user', 'message'];

if (!in_array($target_type, $valid_types, true) || $target_id === 0) {
    JSONResponse::localizedError('invalidReport', 422) -> send();
}

if (strlen($reason) > 65535) {
    JSONResponse::localizedError('reasonIsTooLong', 422) -> send();
}

$target_user_id = ReportManager::resolveTargetUserId($target_type, $target_id);

if ($target_user_id === null) {
    JSONResponse::localizedError('invalidReport', 422) -> send();
}

// A message can only be reported by the person it was sent to. Without this,
// any guessed messageId could be reported, snapshotting a private conversation
// between two other people into the moderation queue.
if ($target_type === 'message' && !ReportManager::messageWasSentTo($target_id, $current_user -> userId)) {
    JSONResponse::localizedError('invalidReport', 422) -> send();
}

$rate_key = 'report:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($rate_key, 20, 3600)) {
    JSONResponse::localizedError('tooManyReportsPleaseTryAgainLater', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

// An encrypted message can't be judged from its row - the server holds only
// ciphertext. The reporter reveals that one message's key (never the
// conversation key: see MessageEnvelope), and two checks make the result
// trustworthy. The franking tag proves the ciphertext is the one the server
// relayed between these two people, and GCM's authentication proves the
// revealed key is the key it was really encrypted under - together, the
// decrypted body genuinely is what was sent, not a fabrication.
$decrypted_body = null;

if ($target_type === 'message') {
    $message = DB::row('
SELECT *
    FROM `Messages`
    WHERE `messageId` = ?
', 'Message', 'i', $target_id);

    if ($message !== null && $message -> bodyCiphertext !== null) {
        $revealed_key = base64_decode((string) ($payload['revealedKey'] ?? ''), true);

        if ($revealed_key === false || strlen($revealed_key) !== 32) {
            JSONResponse::localizedError('unlockTheConversationToReportAnEncryptedMessage', 422) -> send();
        }

        if (!MessageFranking::verify((int) $message -> senderId, (int) $message -> recipientId, $message -> bodyCiphertext, (string) $message -> frankingTag)) {
            JSONResponse::localizedError('thisMessageCouldNotBeVerified', 422) -> send();
        }

        $decrypted_body = MessageEnvelope::decryptWithKey($message -> bodyCiphertext, $revealed_key);

        if ($decrypted_body === null) {
            JSONResponse::localizedError('thisMessageCouldNotBeVerified', 422) -> send();
        }
    }
}

// Reports about the admin - their account, their posts, their messages -
// are dead letters: only the admin and mods see reports, and the admin
// can't be banned, so nobody could ever act on one. Rejected here (not
// just hidden in the UI) so a hand-crafted request can't file one either.
if ($target_user_id === 1) {
    JSONResponse::localizedError('thisContentCanTBeReported', 422) -> send();
}

// A moderator already reviewed and dismissed a report on this content - it
// can't be reported again (posts/messages only; a user carries no such flag).
if (ReportManager::isContentDismissed($target_type, $target_id)) {
    JSONResponse::localizedError('thisContentHasAlreadyBeenReviewedByAModerator', 422) -> send();
}

if (!ReportManager::create($current_user -> userId, $target_type, $target_id, $reason !== '' ? $reason : null, $decrypted_body)) {
    JSONResponse::localizedError('youVeAlreadyReportedThis', 422) -> send();
}

// Tell the post's own moderators too, when it came from another server -
// hiding it here leaves the account carrying on unchallenged everywhere else.
// Sent as the instance, never naming who reported it.
ActivityPubFlag::send($target_type, $target_id, $reason !== '' ? $reason : null);

JSONResponse::success(['reported' => true]) -> send();
