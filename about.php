<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - the site's overview, and the one reachable place linking out to the
// terms of service and privacy policy.
$page = new Page(['title' => 'About']);

$about_card = new Card();
$about_card -> addContents(InfoText::paragraphs(SiteInfo::about()));
$page -> addContent($about_card);

$policy_card = new Card();
$policy_card -> addContent(new SitePolicyLinks());
$page -> addContent($policy_card);

$version = new Paragraph('This site runs ');
$version -> class = 'muted text-sm';
$version -> addContent(new Anchor('https://github.com/FedgeNo/glommer', 'Glommer'));
$version -> addContent(' version ' . GLOMMER_VERSION);

$version_card = new Card();
$version_card -> addContent($version);

// Hardcoded rather than left to the admin-written text above: the GeoNames
// licence (CC BY 4.0) requires attribution wherever its data is used, so it
// ships with the software - on every install whose place directory is
// actually loaded - instead of depending on each admin remembering it.
if (Place::count() > 0) {
    $credit = new Paragraph('Place names from ');
    $credit -> class = 'muted text-sm';
    $credit -> addContent(new Anchor('https://www.geonames.org/', 'GeoNames'));
    $credit -> addContent(', licensed ');
    $credit -> addContent(new Anchor('https://creativecommons.org/licenses/by/4.0/', 'CC BY 4.0'));
    $credit -> addContent('.');

    $version_card -> addContent($credit);
}

$page -> addContent($version_card);

$page -> send();
