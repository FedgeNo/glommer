<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Mod-only forensic media: streams a reported attachment's preserved original
// (uploads/private/originals) - which isn't web-served - so a moderator can view
// media whose post was deleted. Works from the attachment (FeedItem) id alone;
// UploadProcessor::originalForItem finds the file and its type on disk.
//
// The one GET endpoint under /api/, and the one that answers with raw bytes
// rather than JSONResponse: a report card points an <img>/<video>/<audio> src
// straight at it, and a browser can only GET a media src (and expects the media
// itself back, not a JSON envelope). So the POST + JSONResponse convention every
// other /api/ script follows doesn't apply here.
if (!Auth::check() || !Auth::canModerate()) {
    http_response_code(403);
    exit;
}

$item_id = (int) ($_GET['itemId'] ?? 0);

if ($item_id <= 0) {
    http_response_code(400);
    exit;
}

$original = UploadProcessor::originalForItem($item_id);

if ($original === null) {
    http_response_code(404);
    exit;
}

// nosniff so the browser honours the finfo-derived type; private/no-store keeps
// reported media out of shared caches. Streamed inline so an <img>/<video>/
// <audio> in the report card can render it directly.
header('Content-Type: ' . $original['mimeType']);
header('Content-Length: ' . (string) filesize($original['path']));
header('Content-Disposition: inline');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($original['path']);
exit;
