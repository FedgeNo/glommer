<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

// Public - remote Fediverse servers deliver activities here. Every request is
// signature-verified before anything it claims is acted on; an unverified or
// malformed delivery is just dropped (200, so a well-behaved server doesn't
// endlessly retry a delivery this instance will never accept).
$rate_key = 'activitypub-inbox:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 120, 600)) {
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
$host_header = $_SERVER['HTTP_HOST'] ?? null;

if ($signature_header === null || $date_header === null || $digest_header === null || $host_header === null) {
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

if ($signer -> banned === 1) {
    http_response_code(403);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/activitypub/inbox', PHP_URL_PATH) ?? '/activitypub/inbox';

$verified = HTTPSignature::verify('POST', $path, $host_header, $date_header, $digest_header, $signature_header, $signer -> remoteActorPublicKeyPem);

if (!$verified) {
    http_response_code(401);
    exit;
}

$activity = json_decode($body, true);

if (is_array($activity)) {
    ActivityPubInbox::process($activity, $actor_uri);
}

http_response_code(202);
