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

// Posting is paced the way messaging is. A top-level post is written once per
// friend into their feed and a reply notifies the author, so an unpaced client
// costs storage and attention on every account it reaches, not just its own.
// Set well above what writing looks like and below what a script does.
$post_rate_key = 'create-post:' . $current_user -> userId;

if (RateLimiter::tooManyAttempts($post_rate_key, 60, 600)) {
    JSONResponse::error('You\'re posting very quickly. Please wait a moment and try again.', 429) -> send();
}

RateLimiter::recordAttempt($post_rate_key);

// If the whole request body exceeded post_max_size, PHP has already thrown away
// $_POST and $_FILES before this script ran. Catch that here so an oversized
// upload gets a clear "too large" error instead of a misleading "no content" one.
if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0 && $_POST === [] && $_FILES === []) {
    JSONResponse::error('Your upload is too large. The maximum total upload size is ' . ini_get('post_max_size') . 'B.', 413) -> send();
}

$title = ControlCharacters::strip(mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 255));
$description_raw = (string) ($_POST['description'] ?? '');
$link_url = trim((string) ($_POST['linkURL'] ?? ''));
$parent_id = isset($_POST['parentId']) && $_POST['parentId'] !== '' ? (int) $_POST['parentId'] : null;

// The composer's sensitive toggle. Stored whether or not this post ends up
// with media - the author is classifying the post, and media can arrive later
// through the upload worker.
$sensitive = ($_POST['sensitive'] ?? '') === '1' ? 1 : 0;

// The words to read before the post, and only ever alongside that mark: a
// warning on a post nobody flagged has nothing to gate. Optional even then -
// the mark is a complete answer on its own.
$content_warning = $sensitive === 1 ? mb_substr(trim((string) ($_POST['contentWarning'] ?? '')), 0, 255) : '';
$content_warning = $content_warning === '' ? null : $content_warning;

$link_image_seed = trim((string) ($_POST['linkImageSeed'] ?? ''));

// A poll's options arrive as a repeated field, the same way files do. Cleaned
// by Poll rather than here, so the composer and an inbound Question are held to
// one definition of what a usable set of options is.
$poll_options = Poll::cleanOptions(is_array($_POST['pollOptions'] ?? null) ? $_POST['pollOptions'] : []);
$poll_multiple = ($_POST['pollMultiple'] ?? '') === '1';
$poll_duration = (int) ($_POST['pollDuration'] ?? 0);

if (!StagedUploadSeed::belongsTo($link_image_seed, (int) $current_user -> userId) || !UploadProcessor::exists($link_image_seed, 'ImageItem')) {
    $link_image_seed = '';
}

// Optional geolocation the composer's location button attaches - stored exactly,
// not rounded. Both coordinates must be present and in range, or neither is kept.
$latitude = null;
$longitude = null;
$latitude_raw = trim((string) ($_POST['latitude'] ?? ''));
$longitude_raw = trim((string) ($_POST['longitude'] ?? ''));

if ($latitude_raw !== '' && $longitude_raw !== '' && is_numeric($latitude_raw) && is_numeric($longitude_raw)) {
    $latitude_value = (float) $latitude_raw;
    $longitude_value = (float) $longitude_raw;

    if ($latitude_value >= -90 && $latitude_value <= 90 && $longitude_value >= -180 && $longitude_value <= 180) {
        $latitude = $latitude_value;
        $longitude = $longitude_value;
    }
}

// The composer submits the Quill Delta as JSON. Cap the raw input first (bounds
// the decode work), reject a stale client that still POSTs rendered HTML, then
// reduce to the ops we render and derive the plaintext the `description` column
// (and search/meta/RSS) uses. $description_value / $description_delta_value stay
// null for a blank body, so both columns agree there's no rich content.
$description_value = null;
$description_delta_value = null;
$description_ops = [];

if ($description_raw !== '') {
    if (strlen($description_raw) > 262144) {
        JSONResponse::error('Post text is too long', 422) -> send();
    }

    if (!is_array(json_decode($description_raw, true))) {
        JSONResponse::error('Your editor is out of date. Please refresh the page and try again.', 426) -> send();
    }

    $description_ops = Delta::sanitize(Delta::decode($description_raw));
    $description_plaintext = Delta::plainText($description_ops);

    if (strlen($description_plaintext) > 65535) {
        JSONResponse::error('Post text is too long', 422) -> send();
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
        JSONResponse::fieldError('linkURL', 'Give an http:// or https:// link.') -> send();
    }

    if (strlen($link_url) > 255) {
        JSONResponse::fieldError('linkURL', 'That link is too long.') -> send();
    }

    if (!URL::isValidHTTPURL($link_url)) {
        JSONResponse::fieldError('linkURL', 'Point this at a domain name, not an IP address.') -> send();
    }
}

$uploaded_files = $_FILES['files'] ?? null;

// Surface upload failures (most commonly a single file over upload_max_filesize)
// rather than silently skipping the file - otherwise a too-large media upload
// with accompanying text quietly becomes a text-only post the user didn't intend.
// UPLOAD_ERR_NO_FILE is the exception: an empty slot in the files[] array just
// means nothing was attached there, which is fine.
if ($uploaded_files !== null) {
    foreach ($uploaded_files['error'] as $error) {
        if ($error === UPLOAD_ERR_INI_SIZE || $error === UPLOAD_ERR_FORM_SIZE) {
            JSONResponse::error('One of your files is larger than the ' . ini_get('upload_max_filesize') . 'B upload limit.', 413) -> send();
        }

        if ($error !== UPLOAD_ERR_OK && $error !== UPLOAD_ERR_NO_FILE) {
            JSONResponse::error('One of your files failed to upload. Please try again.', 400) -> send();
        }
    }
}

// Refuse uploads outright when the disk is nearly full - the database (on the
// same host) needs the remaining headroom far more than the feed needs
// another upload.
if ($uploaded_files !== null && !UploadProcessor::hasFreeDiskSpace((int) array_sum($uploaded_files['size']))) {
    JSONResponse::error('Uploads are temporarily unavailable - the server is low on storage. Please try again later.', 507) -> send();
}

$has_files = $uploaded_files !== null && count(array_filter($uploaded_files['error'], fn ($error) => $error === UPLOAD_ERR_OK)) > 0;
$has_text = $description_value !== null || $link_url !== '';

// A post is either a media post or a link post, never both. The composer
// enforces this in the UI (each field hides when the other is used), but the
// rule has to hold here too or a direct API call could create a combined
// post - which the renderers deliberately have no layout for.
if ($has_files && $link_url !== '') {
    JSONResponse::error('A post can have either attached files or a link, not both', 422) -> send();
}

$has_poll = $poll_options !== [];

// A poll is a third kind of post and exclusive with the other two for the same
// reason they are with each other: its options are the thing to interact with,
// and there is no layout that sets them beside a gallery or a link preview.
if ($has_poll && ($has_files || $link_url !== '')) {
    JSONResponse::error('A post can have a poll, attached files, or a link - not more than one', 422) -> send();
}

// Counted after Poll::cleanOptions has dropped blanks and duplicates, so this
// is about how many distinct choices there really are rather than how many
// boxes were submitted.
if ($has_poll && count($poll_options) < Poll::MIN_OPTIONS) {
    JSONResponse::error('A poll needs at least ' . Poll::MIN_OPTIONS . ' different options.', 422) -> send();
}

// The question is the post itself - there is nowhere else for it to go, and a
// poll that is only buttons asks nothing.
if ($has_poll && !$has_text) {
    JSONResponse::error('A poll needs a question in the post.', 422) -> send();
}

// A quote post: this one carries a reference to the post it comments on.
// The reference has to name a real post by an unbanned author, and the
// commentary itself is required - a quote with no words of its own is what
// the Repost button is for.
$quoted_post_id = isset($_POST['quotedPostId']) && $_POST['quotedPostId'] !== '' ? (int) $_POST['quotedPostId'] : null;

if ($quoted_post_id !== null) {
    if (!$has_text) {
        JSONResponse::error('A quote post needs words of its own - to pass a post on unchanged, use Repost.', 422) -> send();
    }

    $quoted_exists = DB::row('
SELECT `Posts`.`postId`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`banned` = 0
', \stdClass::class, 'i', $quoted_post_id);

    if ($quoted_exists === null) {
        JSONResponse::error('The post being quoted no longer exists.', 404) -> send();
    }
}

// Checked here as well as in Poll::create, which answers an unusable duration
// with null. Left to that, a mistyped duration would publish the post with its
// poll quietly missing rather than telling anyone.
if ($has_poll && !in_array($poll_duration, Poll::DURATIONS, true)) {
    JSONResponse::error('Choose how long the poll should run.', 422) -> send();
}

// The staged image is the link's preview thumbnail, not standalone media -
// with no link on the post it has nothing to belong to, so discard it.
if ($link_image_seed !== '' && $link_url === '') {
    UploadProcessor::delete($link_image_seed, 'ImageItem', null);
    $link_image_seed = '';
}

if (!$has_text && !$has_files) {
    JSONResponse::error('Post has no content', 422) -> send();
}

if ($parent_id !== null) {
    $parent_post = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $parent_id);

    if ($parent_post === null) {
        JSONResponse::error('Post not found', 404) -> send();
    }

    if (Block::exists($current_user -> userId, (int) $parent_post -> userId)) {
        JSONResponse::error('Unable to reply to this post', 403) -> send();
    }
}

$title_value = $title !== '' ? $title : null;
$link_url_value = $link_url !== '' ? $link_url : null;

$valid_files = [];

if ($has_files) {
    $file_count = count($uploaded_files['name']);

    // One alt text per file, paired by position - the same indexing $_FILES
    // itself uses, appended in the same order by the composer. Absent entirely
    // for an old page still open across a deploy; anything else mismatched is
    // refused rather than guessed at, since a shifted pairing would caption
    // every image with its neighbour's words.
    $alt_texts = $_POST['altTexts'] ?? array_fill(0, $file_count, '');

    if (!is_array($alt_texts) || count($alt_texts) !== $file_count) {
        JSONResponse::error('Alt texts do not match the uploaded files', 422) -> send();
    }

    for ($i = 0; $i < $file_count; $i++) {
        if ($uploaded_files['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $type = UploadProcessor::classify($uploaded_files['tmp_name'][$i]);

        if ($type === null) {
            continue;
        }

        $alt_text = trim((string) ($alt_texts[$i] ?? ''));

        if (mb_strlen($alt_text) > FeedItem::MAX_ALT_TEXT_LENGTH) {
            JSONResponse::error('Alt text is too long (max ' . FeedItem::MAX_ALT_TEXT_LENGTH . ' characters)', 422) -> send();
        }

        $valid_files[] = [
            'tmpPath' => $uploaded_files['tmp_name'][$i],
            'originalFilename' => $uploaded_files['name'][$i],
            'type' => $type,
            // Only an image can carry one - there is nothing an alt text could
            // honestly say about a video or audio row.
            'altText' => $type === 'image' && $alt_text !== '' ? $alt_text : null,
        ];
    }
}

// The earlier guard keyed off the raw upload count; re-check now that files
// have been classified, so an upload that produced no valid media (e.g. a
// plain-text file that classify() rejected) can't slip through as a
// completely contentless post.
if (!$has_text && $valid_files === []) {
    JSONResponse::error('Post has no content', 422) -> send();
}

$needs_async = count(array_filter($valid_files, fn ($file) => $file['type'] !== 'image')) > 0;

// Asked here rather than up with the rest of the quote checks, because it is
// the first point at which what was attached is known - video and audio
// publish through the worker, whose staged batches carry no quoted reference.
if ($needs_async && $quoted_post_id !== null) {
    JSONResponse::error('A quote post can carry images, but not video or audio.', 422) -> send();
}

if ($needs_async) {
    // Paced because staging is not the part that waits. The worker decides how
    // many transcodes run at once, but every batch writes its files to the disk
    // queue the moment it is accepted, so a burst of large video posts fills
    // the disk long before it troubles the CPU.
    $async_upload_rate_key = 'async-upload:' . $current_user -> userId;

    if (RateLimiter::tooManyAttempts($async_upload_rate_key, 5, 600)) {
        JSONResponse::error('Too many video/audio uploads in a short time. Please wait a bit and try again.', 429) -> send();
    }

    RateLimiter::recordAttempt($async_upload_rate_key);

    // Stage the batch and return immediately. The upload-worker service
    // (bin/upload-worker.php) drains the queue at a bounded concurrency, so a
    // transcode waits its turn rather than competing with every other one.
    // Completion is signalled by the postReady/uploadPartlyFailed/uploadFailed
    // notification the worker creates when it finishes.
    UploadBatch::stage($current_user -> userId, $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value, $valid_files, $latitude, $longitude, $sensitive, $content_warning);

    JSONResponse::success(['processing' => true]) -> send();
}

// Read before the row exists, so a post has a language for as long as it has
// existed and nothing ever falls back to what an account setting claims.
$detected_language = LanguageDetector::of($description_value);

// The post and everything that makes it whole - location, hashtags, mentions,
// media rows, poll, timeline fan-out - go in as one transaction, the same
// shape UploadBatch::finalize() uses: no feed ever shows a post whose media
// or poll aren't in place yet, and a failure mid-assembly rolls the whole
// post back (the request's connection closing discards the open transaction)
// instead of leaving a partial one. Media files processed onto disk before a
// rollback are left as invisible orphan files rather than a visibly broken
// post. Notifications and federation fire only AFTER the commit, so a
// rolled-back assembly signals nothing.
mysqli_begin_transaction(DB::connection());

DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `descriptionDelta`, `linkURL`, `sensitive`, `contentWarning`, `quotedPostId`, `detectedLanguage`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
', 'iissssisis', $current_user -> userId, $parent_id, $title_value, $description_value, $description_delta_value, $link_url_value, $sensitive, $content_warning, $quoted_post_id, $detected_language);
$post_id = (int) mysqli_insert_id(DB::connection());

// Coordinates live in their own postId-keyed table, so only a post that
// actually has a location costs a row.
if ($latitude !== null && $longitude !== null) {
    DB::run('
INSERT INTO `PostLocations` (`postId`, `latitude`, `longitude`)
    VALUES (?, ?, ?)
', 'idd', $post_id, $latitude, $longitude);
}

Hashtag::indexPost($post_id, $description_ops);
$mentioned_user_ids = Mention::indexPost($post_id, $description_ops);

// Replies fan out like anything else. A friend's reply is a thing they said,
// and it was only ever held back because, arriving alone in a feed, it read as
// an answer to a question that was not on the page - which the card now says
// for itself.
Timeline::fanOutPost($current_user -> userId, $post_id);

$items = [];

foreach ($valid_files as $file) {
    // Insert a placeholder row first so we have a real, numbered itemId to
    // name the processed files after (no user-controlled filenames survive).
    $placeholder_item_type = 'ImageItem';
    DB::run('
INSERT INTO `FeedItems` (`postId`, `type`)
    VALUES (?, ?)
', 'is', $post_id, $placeholder_item_type);
    $item_id = (int) mysqli_insert_id(DB::connection());

    $result = UploadProcessor::process($file['tmpPath'], $item_id, $file['originalFilename']);

    if ($result === null) {
        DB::run('
DELETE
    FROM `FeedItems`
    WHERE `itemId` = ?
', 'i', $item_id);
        continue;
    }

    // The alt text only lands when the file really processed as an image -
    // classify() guessed from the upload, but process() has the last word.
    $alt_text_value = $result['itemType'] === 'ImageItem' ? $file['altText'] : null;

    DB::run('
UPDATE `FeedItems`
    SET `type` = ?, `altText` = ?
    WHERE `itemId` = ?
', 'ssi', $result['itemType'], $alt_text_value, $item_id);

    $placeholder_row = new FeedItemData();
    $placeholder_row -> itemId = $item_id;
    $placeholder_row -> postId = $post_id;
    $placeholder_row -> type = $result['itemType'];
    $placeholder_row -> altText = $alt_text_value;

    $items[] = FeedItem::fromRow($placeholder_row);
}

if ($link_image_seed !== '') {
    $link_image_item_type = 'ImageItem';
    DB::run('
INSERT INTO `FeedItems` (`postId`, `type`)
    VALUES (?, ?)
', 'is', $post_id, $link_image_item_type);
    $link_image_item_id = (int) mysqli_insert_id(DB::connection());

    UploadProcessor::rename($link_image_seed, $link_image_item_id, 'ImageItem', null);

    $link_image_row = new FeedItemData();
    $link_image_row -> itemId = $link_image_item_id;
    $link_image_row -> postId = $post_id;
    $link_image_row -> type = 'ImageItem';

    $items[] = FeedItem::fromRow($link_image_row);
}

$post = new Post();
$post -> postId = $post_id;
$post -> userId = (int) $current_user -> userId;
$post -> parentId = $parent_id;
$post -> title = $title_value;
$post -> description = $description_value;
$post -> descriptionDelta = $description_delta_value;
$post -> linkURL = $link_url_value;
$post -> createdAt = date('Y-m-d H:i:s');
$post -> latitude = $latitude;
$post -> longitude = $longitude;
$post -> sensitive = $sensitive;
$post -> contentWarning = $content_warning;
$post -> quotedPostId = $quoted_post_id;
$post -> items = $items;
$post -> author = $current_user;

// Attached before the post is announced, because a poll IS the post as far as
// the network reads it: without its choices in place the Create would go out as
// an ordinary Note and the poll would never exist anywhere but here.
if ($has_poll) {
    $post -> poll = Poll::create($post_id, $poll_options, $poll_multiple, $poll_duration);
}

mysqli_commit(DB::connection());

// The post is in and whole - now tell people about it.
Mention::notify($mentioned_user_ids, $current_user -> userId, $post_id);

if ($parent_id !== null) {
    Notification::create((int) $parent_post -> userId, $current_user -> userId, 'reply', $parent_id);
}

// Queued, not delivered: the author waits for their post, not for every server
// that follows them.
FediversePublisher::published($post, $current_user);

JSONResponse::success($post -> toPayload(0, 0, false, false)) -> send();
