<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Users');
$page -> addContent(new UserSearch(Auth::user() -> getSuggestedUsers()));
$page -> send();
