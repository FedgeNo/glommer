<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Server-to-server deliveries never carry or reuse a session cookie, so the
// session init.php opens for every request is dead weight here - one
// orphaned session file per delivery, which on an active instance is
// thousands a day. Nothing below this point reads session state.
//
// Gated on the request actually having presented a cookie: without that
// check this would destroy a real browser session, and since this endpoint
// is necessarily exempt from the CSRF check, any page could then log a
// signed-in visitor out by making their browser POST here.
if (!isset($_COOKIE[session_name()])) {
    session_destroy();
}

// Public - remote Fediverse servers deliver activities here. Every request is
// signature-verified before anything it claims is acted on; an unauthenticated
// delivery is refused (401/403) and a verified but uninteresting one is
// accepted-and-ignored (202), so a well-behaved server doesn't keep retrying
// an activity type this instance has no use for.
//
// clientIP(), not REMOTE_ADDR: behind the reverse proxy this app supports,
// every remote server would otherwise collapse to 127.0.0.1 and a single
// busy peer would rate-limit the whole instance's federation.
$rate_key = 'activitypub-inbox:' . (ServerURL::clientIP() ?? 'unknown');

// A single busy peer legitimately delivers a lot: one activity per post per
// followed account, plus edits, deletes and follow bookkeeping. This is set
// to stop a flood, not to pace normal federation.
if (RateLimiter::tooManyAttempts($rate_key, 1200, 600)) {
    http_response_code(429);
    exit;
}

RateLimiter::recordAttempt($rate_key);

$max_body_bytes = 262144;
$body = file_get_contents('php://input', false, null, 0, $max_body_bytes + 1);

if ($body === false || strlen($body) > $max_body_bytes) {
    http_response_code(413);
    exit;
}

$signature_header = $_SERVER['HTTP_SIGNATURE'] ?? null;
$date_header = $_SERVER['HTTP_DATE'] ?? null;
$digest_header = $_SERVER['HTTP_DIGEST'] ?? null;

if ($signature_header === null || $date_header === null || $digest_header === null) {
    http_response_code(401);
    exit;
}

// A signature is valid forever on its own, so a captured delivery could be
// replayed indefinitely without this.
if (!HTTPSignature::dateIsFresh($date_header)) {
    http_response_code(401);
    exit;
}

// The Digest header is claimed by the sender as part of what it signed - but
// signing a claim doesn't make the claim true. Recomputed from the actual
// received bytes and compared, so a signature that's valid for a DIFFERENT
// body than the one actually sent is caught here, not trusted.
if (!hash_equals(HTTPSignature::digest($body), $digest_header)) {
    http_response_code(401);
    exit;
}

$signature_fields = HTTPSignature::parseSignatureHeader($signature_header);

if ($signature_fields === null) {
    http_response_code(401);
    exit;
}

// keyId is conventionally "<actor URI>#main-key" - the actor URI is what
// identifies who this delivery claims to be from.
$actor_uri = explode('#', $signature_fields['keyId'])[0];

$signer = DB::row('
SELECT *
    FROM `Users`
    WHERE `remoteActorURI` = ?
', 'User', 's', $actor_uri);

// Only ever verifies against a public key already on file for an actor this
// instance actually knows about (someone here follows them) - never fetches
// a key live from whatever the request claims, which would let anyone assert
// any identity by just pointing keyId at a key they control.
if ($signer === null || $signer -> remoteActorPublicKeyPem === null) {
    http_response_code(401);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/activitypub/inbox', PHP_URL_PATH) ?? '/activitypub/inbox';

// Our own canonical host, never the client-supplied Host header. Signing
// `host` is what binds a signature to the server it was meant for; verifying
// it against whatever Host the caller sent would let a delivery captured for
// another server be replayed here, since the caller controls both halves of
// that comparison. Same reasoning the rest of the app uses SERVER_NAME over
// HTTP_HOST for redirects. A legitimate sender delivers to our advertised
// inbox URL, so the host it signs is exactly this value.
$site_url_parts = parse_url((string) Config::get('siteURL'));
$canonical_host = ($site_url_parts['host'] ?? '') . (isset($site_url_parts['port']) ? ':' . $site_url_parts['port'] : '');

// Every header the sender might have signed, by its lowercase name. Host is
// deliberately our own value rather than the received one, for the reason
// above; the rest are as received, since the signature is what vouches for
// them.
$received_headers = ['host' => $canonical_host];

foreach ($_SERVER as $key => $value) {
    if (str_starts_with($key, 'HTTP_') && is_string($value)) {
        $received_headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
    }
}

$received_headers['host'] = $canonical_host;

if (isset($_SERVER['CONTENT_TYPE']) && is_string($_SERVER['CONTENT_TYPE'])) {
    $received_headers['content-type'] = $_SERVER['CONTENT_TYPE'];
}

if (isset($_SERVER['CONTENT_LENGTH']) && is_string($_SERVER['CONTENT_LENGTH'])) {
    $received_headers['content-length'] = $_SERVER['CONTENT_LENGTH'];
}

$verified = HTTPSignature::verify('POST', $path, $received_headers, $signature_header, $signer -> remoteActorPublicKeyPem);

if (!$verified) {
    http_response_code(401);
    exit;
}

// Checked only after the signature proves who this actually is - doing it
// first would answer "is this actor banned here?" to anyone who can guess an
// actor URI, without them having to authenticate at all.
if ($signer -> banned === 1) {
    http_response_code(403);
    exit;
}

$activity = json_decode($body, true);

if (is_array($activity)) {
    ActivityPubInbox::process($activity, $actor_uri);
}

http_response_code(202);
