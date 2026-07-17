<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - visitors deciding whether to sign up need to read this.
$page = new Page(['title' => 'Privacy Policy']);

$page -> addContent(new InfoText(SiteInfo::privacy()));

$page -> send();
