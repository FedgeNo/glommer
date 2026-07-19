<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - visitors deciding whether to sign up need to read this.
$page = new Page(['title' => 'Privacy Policy']);

$privacy_card = new Card();
$privacy_card -> addContents(InfoText::paragraphs(SiteInfo::privacy()));
$page -> addContent($privacy_card);

$page -> send();
