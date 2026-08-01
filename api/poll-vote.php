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
$poll_id = (int) ($payload['pollId'] ?? 0);
$option_ids = is_array($payload['optionIds'] ?? null) ? $payload['optionIds'] : [];

$poll = DB::row('
SELECT *
    FROM `Polls`
    WHERE `pollId` = ?
', 'Poll', 'i', $poll_id);

if ($poll === null) {
    JSONResponse::error('Poll not found', 404) -> send();
}

$author = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $poll -> postId);

// The same wall a reply hits. Answering someone's poll is interacting with
// them, which is exactly what a block is for.
if ($author !== null && Block::exists((int) $current_user -> userId, (int) $author -> userId)) {
    JSONResponse::error('Unable to vote on this poll', 403) -> send();
}

if (!Poll::vote($poll_id, (int) $current_user -> userId, $option_ids)) {
    // One refusal for every reason it can be refused - closed, already
    // answered, or options that are not this poll's. Telling them apart would
    // only tell a caller which guard to try next.
    JSONResponse::error('That vote could not be recorded.', 422) -> send();
}

// Only says anything when the poll came from elsewhere: answering a local one
// is this server's own business, and its author reads the result here.
ActivityPubPollVote::publish($poll, $current_user, $option_ids);

// The whole poll comes back rather than a tally, because a vote changes what
// the poll IS to this reader - controls become results - and the client
// rebuilds it from the same payload the feed would have sent.
$poll -> viewerId = (int) $current_user -> userId;

JSONResponse::success(['poll' => $poll -> toPayload()]) -> send();
