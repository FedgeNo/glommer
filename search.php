<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Search');
$page -> addContent(new PostSearch());
$page -> send();
