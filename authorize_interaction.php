<?php

declare(strict_types=1);

require __DIR__ . '/src/init.php';

/**
 * Where another server sends somebody who wants to act on an account from here.
 *
 * A reader on their own instance presses follow on a profile and is handed to
 * this address with that profile's URL. All this has to do is know who that is:
 * the account is read from its own server, given the row every account from
 * elsewhere gets, and the reader is put on its profile page here - the same
 * page any other account from elsewhere has.
 *
 * Members only, like every other remote profile.
 */
Auth::requireLogin();

$uri = trim((string) ($_GET['uri'] ?? ''));

// Fetching costs a request to somebody else's server, so it is paced - this
// address is one anybody signed in can hand any URL to.
$rate_key = 'authorize-interaction:' . Auth::id();

if (RateLimiter::tooManyAttempts($rate_key, 20, 300)) {
    $words = Strings::for(ErrorDocument::class);
    ErrorDocument::send(429, (string) ($words['tooManyRequestsTitle'] ?? ''), (string) ($words['profileLookupRateLimit'] ?? ''));
    exit;
}

RateLimiter::recordAttempt($rate_key);

$user = RemoteProfileLookup::find($uri);

if ($user === null) {
    require __DIR__ . '/404.php';
    exit;
}

header('Location: ' . ServerURL::absolute('/users/' . rawurlencode((string) $user -> slug) . '/'), true, 302);
