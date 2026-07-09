<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Check Your Inbox');

$page -> addContents(new VerificationNotice());

$page -> send();
