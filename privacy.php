<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - visitors deciding whether to sign up need to read this.
$page = Page::create('Privacy Policy');

$page -> addContent(new PolicyText(SitePolicy::privacy()));

$page -> send();
