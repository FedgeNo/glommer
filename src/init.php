<?php

declare(strict_types=1);

ob_start();

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

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

    // A session can outlive its account (user deleted or banned since login).
    // Treat it as logged out instead of letting every page trip over a null user.
    if ($current_user === null || $current_user -> banned) {
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
