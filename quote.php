<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Composing is a member's act, and there is nothing here for anyone else -
// the post being quoted has its own public page.
Auth::requireLogin();

$quoted = DB::row('
SELECT `Posts`.`postId`, `Posts`.`userId`, `Posts`.`title`, `Posts`.`description`, `Posts`.`createdAt`,
    `Users`.`slug`, `Users`.`title` AS `authorTitle`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` = ? AND `Users`.`banned` = 0
', 'QuotedPost', 'i', (int) ($_GET['post'] ?? 0));

if ($quoted === null) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => (string) (Strings::for('PageTitle')['quote'] ?? ''), 'needsEditor' => true, 'needsMath' => true, 'needsEmoji' => true]);

// What will sit under the words: the same embed the finished post renders,
// so what you see composing is what readers get.
$preview = new Card();
$preview -> addContent($quoted);

$page -> addContent($preview);

$composer = new PostComposer();
$composer -> attributes['data-quoted-post-id'] = (string) $quoted -> postId;
$page -> addContent($composer);

$page -> send();
