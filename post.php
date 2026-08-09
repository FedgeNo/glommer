<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$current_user = Auth::user();
$username = (string) ($_GET['username'] ?? '');
$post_id = (int) ($_GET['id'] ?? 0);

// The counts and the viewer's own like/bookmark come with the row, the same
// way every feed loads them - the action bar is built from the post rather
// than asking after it.
$viewer_id = (int) Auth::id();

$post = DB::row('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts`
    WHERE `Posts`.`postId` = ?
', 'Post', 'iii', $viewer_id, $viewer_id, $post_id);

if ($post === null) {
    require __DIR__ . '/404.php';
    exit;
}

$post = Post::fromRowWithItems($post);

if ($post -> author === null || $post -> author -> slug !== $username || $post -> author -> banned) {
    require __DIR__ . '/404.php';
    exit;
}

// A remote account's post is here only so members can read and reply to what
// they follow - the same rule its author's profile (user.php) and RSS feed
// (user-rss-feed.php) already apply: we don't represent Fediverse content to
// the public, and the remote-media proxy refuses logged-out visitors anyway.
if ($post -> author -> remoteActorURI !== null) {
    Auth::requireLogin();
}

$json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'SocialMediaPosting',
    'headline' => $post -> title ?? $post -> shortDescription(),
    // A summary, not the post. Structured data describes the page for a
    // machine reading about it; the writing itself is the page, and repeating
    // all of it here would send every post twice - once as the markup a person
    // reads and once as JSON nothing renders.
    'description' => truncate((string) $post -> description, Page::META_DESCRIPTION_MAX_LENGTH),
    'datePublished' => $post -> createdAt,
    'url' => current_url(),
];

// Only on a post that was actually edited. A modified date equal to the
// published one claims a revision that never happened.
if ($post -> editedAt !== null) {
    $json_ld['dateModified'] = $post -> editedAt;
}

// Named only when there is a name to give. An author object asserting a null
// name says the post was written by nobody, which is a worse answer than not
// raising the subject.
if ($post -> author !== null) {
    $json_ld['author'] = [
        '@type' => 'Person',
        'name' => $post -> author -> title ?: $post -> author -> slug,
    ];
}

$first_image = null;

foreach ($post -> items as $item) {
    // An image post advertises its full-size render; a video its thumbnail
    // (there's no still frame to link otherwise). Audio has no image at all.
    if ($item instanceof ImageItem) {
        $first_image = $item -> srcURL();
        break;
    }

    if ($item -> imageURL() !== null) {
        $first_image = $item -> imageURL();
        break;
    }
}

if ($first_image !== null) {
    $json_ld['image'] = $first_image;
}

$page = new Page($post);
$page -> image = $first_image;
$page -> jsonLD = $json_ld;
$page -> needsEditor = $current_user !== null;
$page -> needsMath = true;
$page -> needsEmoji = $current_user !== null;

$page -> title = $post -> pageTitle();

// The permalink shows this one post in full: description untruncated, and its
// action bar's Delete redirects home rather than removing a card in place.
$post -> standalone = true;
$post -> truncateDescription = false;

$page -> addContent($post);

if ($current_user !== null) {
    $page -> addContent(new ReplyComposer($post_id));
} else {
    $page -> addContent(new LoginPrompt('reply'));
}

$replies = new ReplyList(['parentId' => $post_id]);

if ($replies -> hasItems()) {
    $page -> addContent(new RepliesHeading());
}

$page -> addContent($replies);

$page -> send();
