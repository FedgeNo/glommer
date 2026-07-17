<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$username = (string) ($_GET['username'] ?? '');

$profile_user = User::byUsername($username);

if ($profile_user === null) {
    require __DIR__ . '/404.php';
    exit;
}

$user_id = (int) $profile_user -> userId;
$name = $profile_user -> title ?? $profile_user -> slug;

$limit = 50;

$feed_rows = DB::rows('
SELECT *
    FROM `Posts`
    WHERE `parentId` IS NULL AND `userId` = ?
    ORDER BY `postId` DESC
    LIMIT ?
', 'Post', 'ii', $user_id, $limit);

$site_title = Config::get('siteTitle');

$feed = new RSSFeed('Posts by ' . $name . ' on ' . $site_title, ServerURL::absolute('/users/' . $profile_user -> slug . '/'), 'Posts by ' . $name . ' on ' . $site_title);

foreach (Post::fromRowsWithItems($feed_rows) as $post) {
    $feed -> addItem(RSSItem::fromPost($post));
}

$feed -> send();
