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

// PostEditor.js sends a JSON body, not form-encoded - $_POST is empty for
// this request, same as api/delete.php.
$payload = json_decode((string) file_get_contents('php://input'), true);
$payload = is_array($payload) ? $payload : [];

$post_id = (int) ($payload['postId'] ?? 0);

$owner = DB::row('
SELECT `userId`, `linkURL`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

if ($owner === null || (int) $owner -> userId !== $current_user -> userId) {
    JSONResponse::localizedError('notYourPost', 403) -> send();
}

// Editing is text/title/link only - attached media (images/video/audio)
// can't be added or removed here (see the class docblock on why: it would
// touch the async upload-processing pipeline, FeedItems, and hashtag
// re-indexing all at once, well beyond "fix a typo"). A post that has no
// text/title/link at all (a pure media post) has nothing this endpoint can
// change, but that's caught below by the same "no content" rule create-post
// already enforces - a media-only post still needs SOME of title/link/body
// to remain non-empty after the edit, same as at creation.
$title = ControlCharacters::strip(mb_substr(trim((string) ($payload['title'] ?? '')), 0, 255));
$description_raw = (string) ($payload['description'] ?? '');
$link_url = trim((string) ($payload['linkURL'] ?? ''));

// Reclassifying media as sensitive, and rewording an image's alt text, are
// the two things about attached media an edit can change: the media itself is
// fixed at creation, but both of those are judgements the author is allowed
// to revise.
$sensitive = ($payload['sensitive'] ?? false) === true ? 1 : 0;

// Only alongside that mark, and optional even then - the same rule the
// composer applies, so an edit cannot produce a post the composer could not.
$content_warning = $sensitive === 1 ? mb_substr(trim((string) ($payload['contentWarning'] ?? '')), 0, 255) : '';
$content_warning = $content_warning === '' ? null : $content_warning;

// itemId => alt text. Validated per entry below, once the post's ownership
// has been established.
$alt_texts = $payload['altTexts'] ?? [];

if (!is_array($alt_texts)) {
    JSONResponse::localizedError('altTextsMustMapItemsToText', 422) -> send();
}

foreach ($alt_texts as $alt_text) {
    if (mb_strlen(trim((string) $alt_text)) > FeedItem::MAX_ALT_TEXT_LENGTH) {
        JSONResponse::localizedError('altTextTooLong', 422, ['count' => FeedItem::MAX_ALT_TEXT_LENGTH]) -> send();
    }
}

// Mirrors create-post.php's Delta validation exactly - same limits, same
// sanitize/plaintext derivation, so an edited post is held to the identical
// rules a new one is.
$description_value = null;
$description_delta_value = null;
$description_ops = [];

if ($description_raw !== '') {
    if (strlen($description_raw) > 262144) {
        JSONResponse::localizedError('postTextIsTooLong', 422) -> send();
    }

    if (!is_array(json_decode($description_raw, true))) {
        JSONResponse::localizedError('yourEditorIsOutOfDatePleaseRefreshThePageAndTryAgain', 426) -> send();
    }

    $description_ops = Delta::sanitize(Delta::decode($description_raw));
    $description_plaintext = Delta::plainText($description_ops);

    if (strlen($description_plaintext) > 65535) {
        JSONResponse::localizedError('postTextIsTooLong', 422) -> send();
    }

    if ($description_plaintext !== '') {
        $description_value = $description_plaintext;
        $description_delta_value = json_encode(['ops' => $description_ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}

if ($link_url !== '') {
    if (!preg_match('/^[a-z][a-z0-9+.\-]*:/i', $link_url)) {
        $link_url = 'https://' . $link_url;
    }

    if (!preg_match('/^https?:\/\//i', $link_url)) {
        JSONResponse::fieldError('linkURL', JSONResponse::localized('validHttpLink')) -> send();
    }

    if (strlen($link_url) > 255) {
        JSONResponse::fieldError('linkURL', JSONResponse::localized('linkTooLong')) -> send();
    }

    if (!URL::isValidHTTPURL($link_url)) {
        JSONResponse::fieldError('linkURL', JSONResponse::localized('domainNotIp')) -> send();
    }
}

$title_value = $title !== '' ? $title : null;
$link_url_value = $link_url !== '' ? $link_url : null;

$media_count_stmt = DB::run('
SELECT COUNT(*) AS `count`
    FROM `FeedItems`
    WHERE `postId` = ?
', 'i', $post_id);
$media_count = (int) mysqli_fetch_assoc(mysqli_stmt_get_result($media_count_stmt))['count'];

// A post that had attached media keeps it regardless of the text edit (media
// isn't editable here), so "no content" only means no title/link/body AND
// no pre-existing media to fall back on.
if ($title_value === null && $link_url_value === null && $description_value === null && $media_count === 0) {
    JSONResponse::localizedError('postHasNoContent', 422) -> send();
}

// Same "media post XOR link post" rule create-post enforces, and it turns on
// which kind of post this already is rather than on whether it holds an item.
// A link post's preview picture is a FeedItem too, so counting items alone
// refused every edit to a link post that had one - the rule fired on a post
// that has never broken it.
$was_link_post = $owner -> linkURL !== null;

if ($link_url_value !== null && $media_count > 0 && !$was_link_post) {
    JSONResponse::localizedError('aPostCanHaveEitherAttachedFilesOrALinkNotBoth', 422) -> send();
}

$edited_at = date('Y-m-d H:i:s');
$detected_language = LanguageDetector::of((string) $description_value);

DB::run('
UPDATE `Posts`
    SET `title` = ?, `description` = ?, `descriptionDelta` = ?, `linkURL` = ?, `sensitive` = ?, `contentWarning` = ?, `editedAt` = ?, `detectedLanguage` = ?
    WHERE `postId` = ?
', 'ssssisssi', $title_value, $description_value, $description_delta_value, $link_url_value, $sensitive, $content_warning, $edited_at, $detected_language, $post_id);

// Each alt text lands only on a row that is this post's own image - the
// WHERE re-checks both, so an itemId belonging to someone else's post (or to
// a video) is simply not matched rather than trusted.
foreach ($alt_texts as $item_id => $alt_text) {
    $alt_text_value = trim((string) $alt_text) !== '' ? trim((string) $alt_text) : null;
    $image_type = 'ImageItem';

    DB::run('
UPDATE `FeedItems`
    SET `altText` = ?
    WHERE `itemId` = ? AND `postId` = ? AND `type` = ?
', 'siis', $alt_text_value, (int) $item_id, $post_id, $image_type);
}

Hashtag::reindexPost($post_id, $description_ops);
Mention::notify(Mention::reindexPost($post_id, $description_ops), $current_user -> userId, $post_id);

// Re-fetch rather than hand-assemble the row: createdAt, parentId, and
// keywords (just rewritten by reindexPost()) all need to reflect the true
// current DB state, not values this script would otherwise have to
// duplicate/guess at. No engagement counts: an edit changes only text/title/
// link, so the client swaps just the post's content and leaves the live
// action bar - counts and all - untouched.
$updated_post = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);
$post = Post::fromRowWithItems($updated_post);
$post -> author = $current_user;

FediversePublisher::updated($post, $current_user);

JSONResponse::success($post -> toPayload(0, 0, false, false)) -> send();
