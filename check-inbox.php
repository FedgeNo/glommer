<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Check Your Inbox']);

$page -> addContent(new VerificationNotice());

$page -> send();
