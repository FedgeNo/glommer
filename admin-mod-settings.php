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

$page = new Page(['title' => (string) (Strings::for('PageTitle')['adminModSettings'] ?? '')]);

// The two that page as you scroll keep their own pages - a list that grows
// under you does not belong inside a settings page beside other things - so
// this links to them rather than swallowing them.
$words = Strings::for('AdminModSettings');
$page -> addContent(new SettingsSection((string) ($words['queues'] ?? ''), new ModQueueLinks()));

// The two that are simply a form and a short list are here in full: sending
// somebody to a page of their own to read six rows was the only reason those
// pages existed.
$page -> addContent(new SettingsSection((string) ($words['blockedServers'] ?? ''), new BlockedServersSetting()));

$page -> addContent(new SettingsSection((string) ($words['bannedTrendingEntities'] ?? ''), new BannedTrendingEntityList()));

$page -> send();
