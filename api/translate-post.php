<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// Members only: the content is public, but each cache miss spends a model
// call this server is accountable for.
Auth::requireLogin();

if (!Translator::canTranslate()) {
    JSONResponse::localizedError('translationIsNotAvailableOnThisServer', 503) -> send();
}

$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$post_id = (int) ($payload['postId'] ?? 0);
$language = PostTranslation::normalizeLanguage((string) ($payload['language'] ?? ''));

if ($post_id < 1 || $language === null) {
    JSONResponse::localizedError('aPostAndALanguageAreRequired', 422) -> send();
}

$post = DB::row('
SELECT `Posts`.`postId`, `Posts`.`description`, `Posts`.`descriptionDelta`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`banned` = 0
', \stdClass::class, 'i', $post_id);

if ($post === null || (string) $post -> description === '') {
    JSONResponse::localizedError('nothingToTranslate', 404) -> send();
}

// From the Delta, not from description: description is the flattened form
// kept for search and meta tags, with every line break collapsed to a space,
// and a model can only give back the lines it was given. Falls back to it for
// a post stored before the rich body existed.
$source = Delta::plainTextInParagraphs(Delta::decode($post -> descriptionDelta));

if ($source === '') {
    $source = (string) $post -> description;
}

// The cache is answered before the rate limit spends anything: a stored
// translation is one indexed read, and the limiter exists to pace model
// calls, not readers.
$cached = PostTranslation::cached($post_id, $language);

if ($cached !== null) {
    JSONResponse::success(['language' => $language, 'body' => $cached]) -> send();
}

if (strlen($source) > PostTranslation::MAX_SOURCE_LENGTH) {
    JSONResponse::localizedError('thisPostIsTooLongToTranslate', 422) -> send();
}

// Told before a slot is taken and before the rate limit counts against them:
// every one of these is settled, so asking again in a minute answers the same,
// and a reader sent away with "try again later" would be waiting on nothing.
$refusal = PostTranslation::refusalFor($post_id, $language, $source);

if ($refusal !== null) {
    JSONResponse::error(PostTranslation::refusalMessage($refusal), 422) -> send();
}

$rate_key = 'translate:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 10, 600)) {
    JSONResponse::localizedError('tooManyTranslationsInAShortTimePleaseWaitABitAndTryAgain', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$translated = PostTranslation::translate($post_id, $language, $source);

if ($translated === null) {
    JSONResponse::localizedError('translationIsNotAvailableRightNowPleaseTryAgainLater', 502) -> send();
}

JSONResponse::success(['language' => $language, 'body' => $translated]) -> send();
