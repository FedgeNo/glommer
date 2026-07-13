<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Fully public - the Help section is readable whether or not you're logged in.
$page = Page::create('Help', 'Guides and answers for using the site.', needsHelp: true);

$page -> addContent(new HelpSearch());

$page -> send();
