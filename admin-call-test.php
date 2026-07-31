<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

Auth::requireLogin();

// Same gate as Site Settings: the primary admin (userId 1) only, not general
// moderators.
if (Auth::id() !== 1) {
    require __DIR__ . '/404.php';
    exit;
}

$page = new Page(['title' => 'Video Call Check']);

// The check negotiates for real, so it needs the same ICE configuration a call
// would use - anything else would be testing a different thing than it reports.
$page -> addContent(new JSGlobals(['iceServers' => VideoCall::iceServers()]));

$page -> addContent(new VideoCallTestPanel());

$page -> send();
