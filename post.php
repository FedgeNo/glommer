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

if ($post -> author === null || $post -> author -> slug !== $username || $post -> author -> banned) {
    require __DIR__ . '/404.php';
    exit;
}

$json_ld = [
    '@context' => 'https://schema.org',
    '@type' => 'SocialMediaPosting',
    'headline' => $post -> title ?? $post -> shortDescription(),
    'articleBody' => $post -> description,
    'datePublished' => $post -> createdAt,
    'url' => Page::currentURL(),
    'author' => [
        '@type' => 'Person',
        'name' => $post -> author !== null ? ($post -> author -> title ?? $post -> author -> slug) : null,
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

$page = new Page(['title' => $post -> title ?? 'Post', 'description' => $post -> description, 'image' => $first_image, 'jsonLd' => $json_ld, 'needsEditor' => $current_user !== null, 'needsMath' => true, 'needsEmoji' => $current_user !== null]);

if ($post -> parentId !== null) {
    $parent_link = ParentPostLink::fromParentId($post -> parentId);

    if ($parent_link !== null) {
        $page -> addContent($parent_link);
    }
}

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
