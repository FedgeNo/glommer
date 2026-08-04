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
// body than the one actually sent is caught here, not trusted. The algorithm
// token is case-insensitive (RFC 3230), so "sha-256=" from another
// implementation is the same claim as "SHA-256=" - only the hash itself is
// compared in constant time.
// RFC 3230 lets a sender state several digests in one header, comma separated,
// so the sha-256 one is picked out rather than the header being assumed to hold
// nothing else - a sender offering "sha-256=...,sha-512=..." is making a
// perfectly ordinary claim. Split on the first = only, since base64 padding is
// itself made of them.
$digest_verified = false;

foreach (explode(',', $digest_header) as $digest_entry) {
    [$digest_algorithm, $digest_value] = array_pad(explode('=', trim($digest_entry), 2), 2, '');

    if (strtolower($digest_algorithm) !== 'sha-256') {
        continue;
    }

    // The first sha-256 claim settles it either way: one that doesn't match is
    // a failed body check, not something to look past in the hope of a second.
    $digest_verified = hash_equals(base64_encode(hash('sha256', $body, true)), $digest_value);

    break;
}

if (!$digest_verified) {
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

// A defederated server is refused before its key is fetched, so a blocked
// domain costs no outbound request - otherwise blocking a hostile server would
// still let it make this one call out on every delivery it sends.
if (BlockedDomain::blocksURL($actor_uri)) {
    http_response_code(403);
    exit;
}

// Fetched from the actor's own server when this instance has not met them
// before, which inbound federation requires: someone following a member here
// is by definition an actor we have never seen, and their key has to come from
// somewhere before their signature can be checked.
//
// That is not the same as trusting whatever the request points keyId at. The
// key is only ever fetched from the actor URI itself, over the guarded fetcher,
// and RemoteActor::fetch refuses a document whose id belongs to another host -
// so a server can still only ever speak for its own accounts, and cannot
// overwrite the cached key of an account already known here.
$signer = RemoteActor::ensureKnown($actor_uri);

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

// A signature that doesn't verify is usually just that. It is also exactly what
// a key rotation looks like from this side - the far server signed with its new
// key while this one still holds the old - and nothing else here ever asks for
// the key again, so without this that account's deliveries fail identically
// from now until somebody edits the database. Rotation is routine and obliged
// after a key is exposed, so this has to recover on its own.
//
// The key is re-read from the actor's own server, never taken from the delivery
// that failed, and the signature is checked again against whatever that server
// serves now. A forged signature just fails twice.
//
// Rate-limited on the actor rather than left to the endpoint's own limiter:
// otherwise anyone could make this server perform an outbound fetch by sending
// one bad signature, as many times as the inbox limiter allows. One attempt per
// actor per window costs a rotation at most a few minutes of delay.
if (!$verified) {
    $refresh_key = 'activitypub-key-refresh:' . $actor_uri;

    if (!RateLimiter::tooManyAttempts($refresh_key, 1, 300)) {
        RateLimiter::recordAttempt($refresh_key);

        $refreshed = RemoteActor::refresh($actor_uri);

        if ($refreshed !== null && is_string($refreshed -> remoteActorPublicKeyPem) && $refreshed -> remoteActorPublicKeyPem !== $signer -> remoteActorPublicKeyPem) {
            $signer = $refreshed;
            $verified = HTTPSignature::verify('POST', $path, $received_headers, $signature_header, $signer -> remoteActorPublicKeyPem);
        }
    }
}

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

// A signature stays valid for as long as its date is fresh, so without this a
// captured delivery could be posted again by anyone who saw it, for the rest
// of that hour. Accepted-and-ignored rather than refused: a sender that never
// got our first answer is entitled to ask again.
if (ActivityPubReplay::seenBefore($signature_header)) {
    http_response_code(202);
    exit;
}

$activity = json_decode($body, true);

if (is_array($activity)) {
    ActivityPubInbox::process($activity, $actor_uri);
}

http_response_code(202);
