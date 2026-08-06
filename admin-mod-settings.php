<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

// Moderators and the admin, the same gate the tools it gathers each applied
// for themselves when they were four separate links.
if (!Auth::canModerate()) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => 'Mod Settings']);

// The two that page as you scroll keep their own pages - a list that grows
// under you does not belong inside a settings page beside other things - so
// this links to them rather than swallowing them.
$page -> addContent(new SettingsSection('Queues', new ModQueueLinks()));

// The two that are simply a form and a short list are here in full: sending
// somebody to a page of their own to read six rows was the only reason those
// pages existed.
$page -> addContent(new SettingsSection('Blocked Servers', new BlockedServersSetting()));

$page -> addContent(new SettingsSection('Banned Trending Entities', new BannedTrendingEntityList()));

$page -> send();
