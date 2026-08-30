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

// Only what a report actually captured. Being mod-gated is not the same as
// being scoped: without this, the kept original of any upload on the server is
// one guessed id away, reported or not, and the kept originals are the copies
// that outlive a deletion. A report card only ever asks for ids out of a
// snapshot, so nothing that should be reachable stops being.
if (!ReportManager::capturedAttachment($item_id)) {
    http_response_code(404);
    exit;
}

$original = UploadProcessor::originalForItem($item_id);

if ($original === null) {
    http_response_code(404);
    exit;
}

// Known-safe media renders in the report card. Everything else is forced to an
// inert download, and every response is sandboxed in case a browser disagrees
// with the MIME decision. private/no-store keeps evidence out of shared caches.
header('Content-Length: ' . (string) filesize($original['path']));
header('Content-Security-Policy: default-src \'none\'; sandbox');

if ($original['mediaType'] === 'file') {
    // Unknown originals are evidence to download, never documents to execute
    // in this origin. Override finfo as well as disposition so a browser has
    // no executable media type to work with.
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="reported-attachment.bin"');
} else {
    header('Content-Type: ' . $original['mimeType']);
    header('Content-Disposition: inline');
}

header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');

readfile($original['path']);
exit;
