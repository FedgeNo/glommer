<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Users']);
$page -> addContent(new UserSearch(new EligibleSuggestedUserList((int) Auth::user() -> userId) -> rows()));
$page -> send();
