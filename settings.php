<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Settings');

$page -> addContent(new Heading2('Change Password'));

$page -> addContent(new ChangePasswordForm());

$page -> addContent(new Heading2('Change Email'));

$page -> addContent(new ChangeEmailForm());

$page -> addContent(new Heading2('Theme'));

$page -> addContent(new ThemeSelector());

// The site needs at least one admin account to function - api/delete-account.php
// rejects userId 1 too, but there's no reason to show the form at all here.
if ((int) Auth::user() -> userId !== 1) {
    $page -> addContent(new Heading2('Delete Account'));

    $page -> addContent(new DeleteAccountForm());
}

$page -> send();
