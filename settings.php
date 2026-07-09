<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Settings');

$page -> addContents(new Heading2('Change Password'));

$page -> addContents(new ChangePasswordForm());

$page -> addContents(new Heading2('Theme'));

$page -> addContents(new ThemeSelector());

$page -> send();
