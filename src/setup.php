<?php

declare(strict_types=1);

// This script is reached whenever Database::connection() fails. If .env
// already exists, that means an ESTABLISHED install whose database is down
// or misconfigured - not a fresh one. Never show the setup wizard in that
// state: it writes .env, so exposing it during an outage would let any
// visitor point the site at their own database. Show a maintenance page
// instead. (userId is dropped from the in-memory session because rendering
// a page for a logged-in user needs the database; session_abort() closes
// the session without saving, so nobody actually gets logged out by this.)
if (is_file(__DIR__ . '/../.env')) {
    unset($_SESSION['userId']);
    session_abort();
    http_response_code(503);

    $page = Page::create('Site Unavailable');
    $page -> addContents(new Paragraph('The site can\'t reach its database right now. Please try again in a few minutes.'));
    $page -> addContents(new Notice('If you run this site: the database connection using the credentials in .env is failing - check that the database server is running and that those credentials are still valid. This page is shown instead of the setup wizard precisely so a database outage can\'t be used to reconfigure the site.'));
    $page -> send();
    exit;
}

// A leftover session from before a reinstall would make Page::create() try
// (and fail) to load its user from the not-yet-configured database.
unset($_SESSION['userId']);

$environment_errors = [];

foreach (EnvironmentChecker::checks() as $result) {
    if (!$result['ok']) {
        $environment_errors[] = $result['message'];
    }
}

$errors = [];
$success = false;

if ($environment_errors === [] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['CSRFToken'] ?? null;

    if (!CSRF::verify(is_string($csrf_token) ? $csrf_token : null)) {
        $errors[] = 'Your session expired or the form was tampered with. Please reload and try again.';
    } else {
        $site_url = trim((string) ($_POST['siteURL'] ?? ''));
        $site_title = trim((string) ($_POST['siteTitle'] ?? ''));
        $mail_from_address = trim((string) ($_POST['mailFromAddress'] ?? ''));
        $mail_from_name = trim((string) ($_POST['mailFromName'] ?? ''));
        $db_host = trim((string) ($_POST['DBHost'] ?? ''));
        $db_port = trim((string) ($_POST['DBPort'] ?? ''));
        $db_database = trim((string) ($_POST['DBDatabase'] ?? ''));
        $admin_username = (string) ($_POST['adminUsername'] ?? '');
        $admin_password = (string) ($_POST['adminPassword'] ?? '');

        if ($site_url === '' || filter_var($site_url, FILTER_VALIDATE_URL) === false) {
            $errors[] = 'A valid site URL is required.';
        }

        if ($site_title === '') {
            $errors[] = 'A site title is required.';
        }

        if ($mail_from_address === '' || filter_var($mail_from_address, FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = 'A valid mail from address is required.';
        }

        if ($mail_from_name === '') {
            $errors[] = 'A mail from name is required.';
        }

        if (!preg_match('/^[A-Za-z0-9_.:-]+$/', $db_host)) {
            $errors[] = 'Database host contains invalid characters.';
        }

        if (!preg_match('/^[0-9]{1,5}$/', $db_port) || (int) $db_port < 1 || (int) $db_port > 65535) {
            $errors[] = 'Database port must be a number between 1 and 65535.';
        }

        if (!preg_match('/^[A-Za-z0-9_]{1,64}$/', $db_database)) {
            $errors[] = 'Database name may only contain letters, numbers, and underscores.';
        }

        if ($admin_username === '') {
            $errors[] = 'A database admin username is required.';
        }

        $admin_connection = null;

        if ($errors === []) {
            try {
                $admin_connection = mysqli_connect($db_host, $admin_username, $admin_password, null, (int) $db_port);
            } catch (\mysqli_sql_exception $exception) {
                $errors[] = 'Could not connect with the database admin credentials: ' . $exception -> getMessage();
            }
        }

        $runtime_account = null;

        if ($errors === []) {
            try {
                $runtime_account = Installer::provisionDatabase($admin_connection, $db_database);
                mysqli_close($admin_connection);
            } catch (\mysqli_sql_exception $exception) {
                $errors[] = 'Database setup failed: ' . $exception -> getMessage();
            } catch (\RuntimeException $exception) {
                $errors[] = $exception -> getMessage();
            }
        }

        if ($errors === []) {
            // The WebSocket daemon is already running by this point (the
            // environment check above requires it) using whatever host/port
            // currently resolve from config.php - carried forward unchanged
            // so the already-running process doesn't need to change how
            // it's listening. Its secret is regenerated fresh here exactly
            // like the DB password, which does mean the daemon needs
            // restarting once (see the success page) to pick up this new
            // value - it can't already know a secret that didn't exist yet.
            $existing_config = require __DIR__ . '/config.php';
            $ws_secret = bin2hex(random_bytes(32));

            $env_contents = Installer::envContents([
                'DB_HOST' => $db_host,
                'DB_PORT' => $db_port,
                'DB_DATABASE' => $db_database,
                'DB_USERNAME' => $runtime_account['username'],
                'DB_PASSWORD' => $runtime_account['password'],
                'MAIL_FROM_ADDRESS' => $mail_from_address,
                'MAIL_FROM_NAME' => $mail_from_name,
                'SITE_URL' => $site_url,
                'SITE_TITLE' => $site_title,
                'WS_HOST' => $existing_config['WSHost'],
                'WS_PORT' => (string) $existing_config['WSPort'],
                'WS_PUSH_PORT' => (string) $existing_config['WSPushPort'],
                'WS_SECRET' => $ws_secret,
            ]);

            if (file_put_contents(__DIR__ . '/../.env', $env_contents) === false) {
                $errors[] = 'Could not write .env - check that the web server user can write to the project root.';
            } else {
                $success = true;
            }
        }
    }
}

if ($success) {
    $page = Page::create('Setup Complete');

    $page -> addContents(new Paragraph('Setup finished - the database, a least-privilege runtime account, and .env are all in place. Three small steps remain:'));
    $page -> addContents(new SetupNextSteps());
} elseif ($environment_errors !== []) {
    $page = Page::create('Set Up');

    $page -> addContents(new Paragraph('Welcome! Before setup can continue, this server is missing some prerequisites:'));
    $page -> addContents(new ErrorList($environment_errors));
    $page -> addContents(new Notice('Fix these on the server, then reload this page to re-check.'));
} else {
    $page = Page::create('Set Up');

    if ($errors !== []) {
        $page -> addContents(new ErrorList($errors));
    }

    $page -> addContents(new Paragraph('Welcome! All environment checks passed. Submitting this form creates the database (if it doesn\'t exist yet), a least-privilege runtime database account with a random password, and the schema, then writes it all to .env. The admin credentials are used once for provisioning and are never stored.'));
    $page -> addContents(new SetupForm());
}

$page -> send();
