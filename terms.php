<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - visitors deciding whether to sign up need to read this.
$page = new Page(['title' => 'Terms of Service']);

$page -> addContent(new InfoText(SiteInfo::terms()));

$page -> send();
