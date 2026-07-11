<?php

declare(strict_types=1);

ob_start();

// The version of this codebase. The database records the version it was last
// installed/upgraded to (the appVersion setting, written by bin/install.php and
// the web setup wizard); a mismatch means "run the upgrade" and locks the site
// to a maintenance page below until the two agree.
const GLOMMER_VERSION = '0.9.0';

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// An https SITE_URL declares the site is meant to be reached over TLS -
// permanently redirect any plain-HTTP request to the canonical https URL
// before anything else happens (no session cookie should ever travel over
// plain HTTP there). Local development installs use an http SITE_URL and are
// unaffected. X-Forwarded-Proto covers a TLS-terminating reverse proxy,
// where PHP itself sees plain HTTP on every request.
$init_config = require __DIR__ . '/config.php';

if (
    str_starts_with((string) $init_config['siteURL'], 'https://')
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
        header('Location: ' . URL::absolute('/signup/'));
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

    $exempt_scripts = ['check-inbox.php', 'logout.php', 'verify-email.php', 'resend-verification.php'];

    if (!$current_user -> verified && !in_array(basename($_SERVER['SCRIPT_FILENAME']), $exempt_scripts, true)) {
        if (str_contains($_SERVER['SCRIPT_FILENAME'], '/api/')) {
            JSONResponse::error('Email verification required', 403) -> send();
        }

        header('Location: ' . URL::absolute('/check-inbox/'));
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
