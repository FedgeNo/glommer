<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Fully public - the Help section is readable whether or not you're logged in.
$page = new Page(['title' => (string) (Strings::for('PageTitle')['help'] ?? ''), 'description' => (string) (Strings::for('PageTitle')['helpDescription'] ?? ''), 'needsHelp' => true]);

$page -> addContent(new HelpSearch());

$page -> send();
