<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$current_user = Auth::user();
$username = (string) ($_GET['username'] ?? '');
$post_id = (int) ($_GET['id'] ?? 0);

$post = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

if ($post === null) {
    require __DIR__ . '/404.php';
    exit;
}

$post = Post::fromRowWithItems($post);

if ($post -> author === null || $post -> author -> username !== $username || $post -> author -> banned) {
    require __DIR__ . '/404.php';
    exit;
}

$not_banned = 0;
$limit = 20;
$fetch_limit = $limit + 1;

$reply_rows = DB::rows('
SELECT `Posts`.*
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` = ? AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'Post', 'iii', $post_id, $not_banned, $fetch_limit);

$json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'SocialMediaPosting',
    'headline' => $post -> title ?? $post -> shortDescription(),
    'articleBody' => $post -> description,
    'datePublished' => $post -> createdAt,
    'url' => Page::currentURL(),
    'author' => [
        '@type' => 'Person',
        'name' => $post -> author !== null ? ($post -> author -> displayName ?? $post -> author -> username) : null,
    ],
];

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

$page = Page::create($post -> title ?? 'Post', $post -> description, $first_image, $json_ld, needsEditor: $current_user !== null, needsMath: true, needsEmoji: $current_user !== null);

if ($post -> parentId !== null) {
    $parent_link = ParentPostLink::fromParentId($post -> parentId);

    if ($parent_link !== null) {
        $page -> addContent($parent_link);
    }
}

$page -> addContent(PostPage::fromPost($post));

if ($current_user !== null) {
    $page -> addContent(new ReplyComposer($post_id));
} else {
    $page -> addContent(new LoginPrompt('reply'));
}

$has_more_replies = count($reply_rows) > $limit;

if ($has_more_replies) {
    array_pop($reply_rows);
}

if ($reply_rows !== []) {
    $page -> addContent(new RepliesHeading());
}

$page -> addContent(ReplyList::fromRows($post_id, $reply_rows, $has_more_replies));

$page -> send();
