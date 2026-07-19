<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the site's overview, and the one reachable place linking out to the
// terms of service and privacy policy.
$page = new Page(['title' => 'About']);

$page -> addContent(new InfoText(SiteInfo::about()));

$page -> addContent(new SitePolicyLinks());

$version = new Paragraph('Software version ' . GLOMMER_VERSION);
$version -> class = 'Muted text-sm';
$page -> addContent($version);

$page -> send();
