<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the site's overview, and the one reachable place linking out to the
// terms of service and privacy policy.
$page = new Page(['title' => 'About']);

$page -> addContent(new InfoText(SiteInfo::about()));

$info_links = new Div();
$info_links -> class = 'd-flex gap-3';

$terms_link = new Anchor(ServerURL::absolute('/terms'), 'Terms of Service');
$terms_link -> class = 'Btn';
$info_links -> addContent($terms_link);

$privacy_link = new Anchor(ServerURL::absolute('/privacy'), 'Privacy Policy');
$privacy_link -> class = 'Btn';
$info_links -> addContent($privacy_link);

$page -> addContent($info_links);

$page -> send();
