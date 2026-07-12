<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

$page = Page::create('Settings');

$page -> addContents(new Heading2('Change Password'));

$page -> addContents(new ChangePasswordForm());

$page -> addContents(new Heading2('Change Email'));

$page -> addContents(new ChangeEmailForm());

$page -> addContents(new Heading2('Theme'));

$page -> addContents(new ThemeSelector());

// The site needs at least one admin account to function - api/delete-account.php
// rejects userId 1 too, but there's no reason to show the form at all here.
if ((int) Auth::user() -> userId !== 1) {
    $page -> addContents(new Heading2('Delete Account'));

    $page -> addContents(new DeleteAccountForm());
}

$page -> send();
