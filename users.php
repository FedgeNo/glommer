<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Users');
$page -> addContents(new UserSearch(Auth::user() -> getSuggestedUsers()));
$page -> send();
