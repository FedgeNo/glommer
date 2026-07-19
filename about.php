<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the site's overview, and the one reachable place linking out to the
// terms of service and privacy policy.
$page = new Page(['title' => 'About']);

$page -> addContent(new InfoText(SiteInfo::about()));

$page -> addContent(new SitePolicyLinks());

$version = new Paragraph('This site runs ');
$version -> class = 'Muted text-sm';
$version -> addContent(new Anchor('https://github.com/FedgeNo/glommer', 'Glommer'));
$version -> addContent(' version ' . GLOMMER_VERSION);
$page -> addContent($version);

$page -> send();
