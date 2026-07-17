<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - visitors deciding whether to sign up need to read this.
$page = new Page(['title' => 'Terms of Service']);

$page -> addContent(new PolicyText(SitePolicy::terms()));

$page -> send();
