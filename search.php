<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = new Page(['title' => 'Search']);
$page -> addContent(new PostSearch());
$page -> send();
