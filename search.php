<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => (string) (Strings::for('PageTitle')['search'] ?? ''), 'needsEditor' => Auth::check()]);
$page -> addContent(new PostSearch());
$page -> addContent(new SearchFeedSection());
$page -> send();
