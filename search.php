<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Search', 'needsEditor' => Auth::check()]);
$page -> addContent(new PostSearch());
$page -> addContent(new SearchFeedSection());
$page -> send();
