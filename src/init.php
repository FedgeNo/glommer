<?php

declare(strict_types=1);

ob_start();

// The version of this codebase. The database records the version it was last
// installed/upgraded to (the appVersion setting, written by bin/install.php and
// the web setup wizard); a mismatch means "run the upgrade" and locks the site
// to a maintenance page below until the two agree.
const GLOMMER_VERSION = '0.9.2';

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// HTTPS is required on an installed site - nothing is served over plain HTTP
// except the redirect pointing at the https URL (the .htaccess enforces the
// same for static files Apache serves without PHP). Only a fresh,
// not-yet-installed site (no .env) is exempt: the setup wizard has to stay
// reachable before TLS is necessarily configured, and with no .env the
// config's SITE_URL is just the placeholder default anyway. X-Forwarded-Proto
// covers a TLS-terminating reverse proxy, where PHP sees plain HTTP on every
// request. An installed site whose SITE_URL is still http:// is refused
// further down (after the error handlers are in place) rather than served.
$init_config = require __DIR__ . '/config.php';
$site_is_installed = is_file(__DIR__ . '/../.env');

if (
    $site_is_installed
    && str_starts_with((string) $init_config['siteURL'], 'https://')
    && ($_SERVER['HTTPS'] ?? '') === ''
    && strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) !== 'https'
) {
    header('Location: ' . URL::absolute($_SERVER['REQUEST_URI'] ?? '/'), true, 301);
    exit;
}

session_set_cookie_params([
    'httponly' => true,
    'samesite' => 'Lax',
    'secure' => ($_SERVER['HTTPS'] ?? '') !== '',
]);
session_start();

SecurityHeaders::send();

try {
    Database::connection();
} catch (\mysqli_sql_exception $exception) {
    require __DIR__ . '/setup.php';
    exit;
}

// A mid-request failure shouldn't leak a raw stack trace (or a blank
// white-screen 500) to whoever's looking - /api/ callers always need JSON,
// everyone else gets the same friendly error page every other failure mode
// here (CSRF, 404, ...) already uses. Covers both an uncaught Throwable and
// (via the shutdown function) the rarer fatal that isn't one, like hitting
// memory_limit - anything already mid-way through streaming a real response
// is left alone rather than being clobbered.
$send_server_error = function (): void {
    if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
        JSONResponse::error('Server error', 500) -> send();
    } else {
        ErrorDocument::send(500, 'Something Went Wrong', 'An unexpected error occurred. Please try again, and let us know if it keeps happening.');
    }
};

set_exception_handler(function (\Throwable $exception) use ($send_server_error): void {
    error_log((string) $exception);
    $send_server_error();
});

register_shutdown_function(function () use ($send_server_error): void {
    $error = error_get_last();
    $fatal_error_types = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR];

    if ($error === null || !in_array($error['type'], $fatal_error_types, true) || headers_sent()) {
        return;
    }

    error_log($error['message'] . ' in ' . $error['file'] . ':' . $error['line']);
    $send_server_error();
});

// HTTPS is a requirement, not a preference: an installed site whose SITE_URL
// is still http:// gets a configuration-error page instead of being served -
// both installers refuse an http URL, so this only catches a hand-edited
// .env. (Placed here, after the error handlers, where ErrorDocument can
// safely render.)
if ($site_is_installed && !str_starts_with((string) $init_config['siteURL'], 'https://')) {
    if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
        JSONResponse::error('This site requires HTTPS and is misconfigured. The administrator must set SITE_URL to an https:// URL.', 503) -> send();
    }

    ErrorDocument::send(503, 'HTTPS Required', 'This site requires HTTPS, but its SITE_URL is configured as plain http. The administrator must set SITE_URL in .env to an https:// URL - see the README\'s HTTPS section for how to get a certificate (including for localhost).');
    exit;
}

// Version gate: the code and the database must have been installed/upgraded
// together. After a code update, the database stays at the old version until
// `php bin/install.php` applies the migrations and records the new version -
// running mismatched risks subtle breakage (queries against columns that don't
// exist yet), so lock everyone out with a maintenance page instead. The admin
// (userId 1) gets the specific mismatch and the fix; everyone else gets a
// generic "upgrading" message.
$db_app_version = Settings::get('appVersion');

if ($db_app_version !== GLOMMER_VERSION) {
    if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
        JSONResponse::error('The site is being upgraded. Please try again in a few minutes.', 503) -> send();
    }

    $maintenance_message = Auth::id() === 1
        ? 'The code is version ' . GLOMMER_VERSION . ' but the database is at ' . ($db_app_version ?? 'an unknown version') . '. Run "php bin/install.php" to bring the database up to date.'
        : 'The site is being upgraded and will be back shortly.';

    ErrorDocument::send(503, 'Upgrade In Progress', $maintenance_message);
    exit;
}

// Persistent "Remember me" login: a request arriving without a session but
// with a valid remember-me cookie gets its session re-established (and the
// used token rotated) before anything below asks who's logged in.
if (!Auth::check()) {
    RememberToken::loginFromCookie();
}

if (!Auth::check() && basename($_SERVER['SCRIPT_FILENAME']) !== 'signup.php') {
    $user_count_result = mysqli_query(Database::connection(), '
SELECT COUNT(*) AS `count`
    FROM `Users`
');

    if ((int) mysqli_fetch_assoc($user_count_result)['count'] === 0) {
        header('Location: ' . URL::absolute('/signup'));
        exit;
    }
}

if (Auth::check()) {
    $current_user = Auth::user();

    // A session can outlive its account (user deleted or banned since login)
    // or its credentials (password changed since login bumps sessionVersion,
    // and this session recorded the old one). Treat it as logged out instead
    // of letting every page trip over a null user - or letting a stolen
    // session survive a password change.
    if ($current_user === null || $current_user -> banned || ($_SESSION['sessionVersion'] ?? 0) !== $current_user -> sessionVersion) {
        Auth::logout();

        if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
            JSONResponse::error('Not logged in', 401) -> send();
        }

        header('Location: ' . URL::absolute('/'));
        exit;
    }

    $exempt_scripts = ['check-inbox.php', 'logout.php', 'verify-email.php', 'resend-verification.php', 'revert-email.php'];

    if (!$current_user -> verified && !in_array(basename($_SERVER['SCRIPT_FILENAME']), $exempt_scripts, true)) {
        if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
            JSONResponse::error('Email verification required', 403) -> send();
        }

        header('Location: ' . URL::absolute('/check-inbox'));
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['CSRFToken'] ?? null;

    if (!CSRF::verify(is_string($csrf_token) ? $csrf_token : null)) {
        if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
            JSONResponse::error('Invalid CSRF token', 403) -> send();
        }

        ErrorDocument::send(403, 'Forbidden', 'Your session expired or the form was tampered with. Please go back and try again.');
        exit;
    }
}
