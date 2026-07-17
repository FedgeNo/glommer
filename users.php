<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Users']);
$page -> addContent(new UserSearch(Auth::user() -> getSuggestedUsers()));
$page -> send();
