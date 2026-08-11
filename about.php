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
if (Place::any()) {
    $credit = new Paragraph('Place names from ');
    $credit -> class = 'muted text-sm';
    $credit -> addContent(new Anchor('https://www.geonames.org/', 'GeoNames'));
    $credit -> addContent(', licensed ');
    $credit -> addContent(new Anchor('https://creativecommons.org/licenses/by/4.0/', 'CC BY 4.0'));
    $credit -> addContent('.');

    $version_card -> addContent($credit);
}

// The map already credits its tiles on the map itself, where Leaflet puts
// them; this is the same credit somewhere a reader can find it without
// opening a map. Read from the configured source rather than hardcoded,
// because which tiles are in use is an admin's choice and crediting the wrong
// service is worse than crediting none - and stripped to its text, since an
// attribution is a line to read here rather than markup to run.
$tiles = trim(strip_tags(MapTiles::attribution()));

if ($tiles !== '') {
    $map_credit = new Paragraph('Map tiles from ');
    $map_credit -> class = 'muted text-sm';
    $map_credit -> addContent($tiles);
    $map_credit -> addContent('.');

    $version_card -> addContent($map_credit);
}

// The MIT licence asks for a notice in copies of the software, and nothing is
// being copied here - this is thanks rather than compliance. Translating on
// this machine instead of somebody's API is the reason a post never leaves the
// server to be read in another language, which seems worth saying out loud.
if (Translator::isAvailable()) {
    $translation = new Paragraph('Translation by ');
    $translation -> class = 'muted text-sm';
    $translation -> addContent(new Anchor('https://www.argosopentech.com/', 'Argos Translate'));
    $translation -> addContent(', which runs here rather than anywhere else.');

    $version_card -> addContent($translation);
}

$page -> addContent($version_card);

$page -> send();
