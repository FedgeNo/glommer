<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

$page = Page::create('Trending Topics', 'What people are talking about on ' . Config::get('siteTitle') . ' right now.');
$page -> addContent(new TrendingSection(Trending::current(50)));
$page -> send();
