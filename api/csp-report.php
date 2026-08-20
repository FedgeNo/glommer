<?php

declare(strict_types=1);

require __DIR__ . '/api-init.php';

// Every /api/ endpoint requires POST - init.php's centralized CSRF check only
// covers POST requests, so a GET-reachable endpoint would bypass it.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JSONResponse::localizedError('methodNotAllowed', 405) -> send();
}

// Public and unauthenticated: the sender is any visitor's browser acting on
// the CSP report-uri directive, with no session and no token of ours - which
// is why init.php exempts this script from the CSRF check. The size cap, the
// shape checks below and the rate limit are the whole guard.
$rate_key = 'csp-report:' . (ServerURL::clientIP() ?? 'unknown');

if (RateLimiter::tooManyAttempts($rate_key, 30, 300)) {
    JSONResponse::localizedError('tooManyAttemptsPleaseTryAgainLater', 429) -> send();
}

RateLimiter::recordAttempt($rate_key);

$body = (string) file_get_contents('php://input', false, null, 0, CSPReport::MAX_BODY_BYTES + 1);

if ($body === '' || strlen($body) > CSPReport::MAX_BODY_BYTES) {
    JSONResponse::localizedError('requestBodyTooLarge', 413) -> send();
}

$decoded = json_decode($body, true);
$user_agent = is_string($_SERVER['HTTP_USER_AGENT'] ?? null) ? $_SERVER['HTTP_USER_AGENT'] : null;
$field = static fn (array $details, string $key): ?string => is_string($details[$key] ?? null) ? $details[$key] : null;

if (is_array($decoded) && is_array($decoded['csp-report'] ?? null)) {
    // The report-uri format the CSP header asks for: one violation per POST,
    // wrapped in a csp-report object. Stored exactly as it arrived.
    $details = $decoded['csp-report'];

    CSPReport::record(
        $field($details, 'violated-directive') ?? $field($details, 'effective-directive'),
        $field($details, 'blocked-uri'),
        $user_agent,
        $body
    );
} elseif (is_array($decoded) && array_is_list($decoded)) {
    // The Reporting API batch format, in case a browser sends it here anyway:
    // a list of typed reports, each stored as its own row.
    $stored = 0;

    foreach (array_slice($decoded, 0, 10) as $entry) {
        if (!is_array($entry) || ($entry['type'] ?? null) !== 'csp-violation' || !is_array($entry['body'] ?? null)) {
            continue;
        }

        CSPReport::record(
            $field($entry['body'], 'effectiveDirective'),
            $field($entry['body'], 'blockedURL'),
            $user_agent,
            (string) json_encode($entry)
        );
        $stored++;
    }

    if ($stored === 0) {
        JSONResponse::localizedError('invalidRequest', 400) -> send();
    }
} else {
    JSONResponse::localizedError('invalidRequest', 400) -> send();
}

// Browsers ignore the response entirely; 204 is the conventional acknowledgement.
http_response_code(204);
