<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Drafts & Scheduled']);

$page -> addContent(new StagedPostList(['userId' => (int) Auth::id()]));

$page -> send();
