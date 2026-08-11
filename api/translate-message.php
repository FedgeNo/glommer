<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::error('Method not allowed', 405) -> send();
}

Auth::requireLogin();

if (!Translator::isAvailable()) {
    JSONResponse::error('Translation is not available on this server.', 503) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);

$message_id = (int) ($payload['messageId'] ?? 0);
$language = PostTranslation::normalizeLanguage((string) ($payload['language'] ?? ''));

if ($message_id < 1 || $language === null) {
    JSONResponse::error('A message and a language are required', 422) -> send();
}

// Only a message this member is party to, and only one somebody else sent -
// the button is offered on nothing else, and an endpoint that trusted the
// button would translate any message on the server for anyone who guessed an
// id.
$viewer_id = (int) Auth::id();

$message = DB::row('
SELECT `messageId`, `senderId`, `recipientId`, `body`, `bodyCiphertext`
    FROM `Messages`
    WHERE `messageId` = ? AND `recipientId` = ?
', \stdClass::class, 'ii', $message_id, $viewer_id);

if ($message === null) {
    JSONResponse::error('No such message', 404) -> send();
}

// Where the server holds the words, the server reads them: taking the caller's
// copy instead would make this a translation service that happens to check an
// id. Only an end-to-end encrypted message has no readable body here, and for
// that one the browser is the only place the text exists - which is the whole
// reason the reader is warned before the first one goes out.
if ((string) $message -> body !== '') {
    $source_text = (string) $message -> body;
} elseif ($message -> bodyCiphertext !== null) {
    $source_text = trim((string) ($payload['text'] ?? ''));
} else {
    JSONResponse::error('Nothing to translate', 404) -> send();
}

if ($source_text === '') {
    JSONResponse::error('Nothing to translate', 404) -> send();
}

if (strlen($source_text) > PostTranslation::MAX_SOURCE_LENGTH) {
    JSONResponse::error('This message is too long to translate.', 422) -> send();
}

// Shared with the post translator on purpose: the cost being paced is this
// server's translator, and it does not care which page asked.
$rate_key = 'translate:' . $viewer_id;

if (RateLimiter::tooManyAttempts($rate_key, 10, 600)) {
    JSONResponse::error('Too many translations in a short time. Please wait a bit and try again.', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

// Nothing is written down. A post's translation is cached because a post is
// public and the same one is read by everybody; a message is one conversation's
// own, and a second copy of it in another table is a second thing to leak.
$translated = Translator::translate($source_text, $language, null);

if ($translated === null) {
    JSONResponse::error('Translation is not available right now. Please try again later.', 502) -> send();
}

JSONResponse::success(['language' => $language, 'body' => $translated]) -> send();
