<?php

declare(strict_types=1);

$environment_errors = [];

foreach (EnvironmentChecker::checks() as $result) {
    if (!$result['ok']) {
        $environment_errors[] = $result['message'];
    }
}

$errors = [];
$success = false;

if ($environment_errors === [] && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrfToken'] ?? null;

    if (!CSRF::verify(is_string($csrf_token) ? $csrf_token : null)) {
        $errors[] = 'Your session expired or the form was tampered with. Please reload and try again.';
    } else {
        $site_url = trim((string) ($_POST['siteURL'] ?? ''));
        $site_title = trim((string) ($_POST['siteTitle'] ?? ''));
        $mail_from_address = trim((string) ($_POST['mailFromAddress'] ?? ''));
        $mail_from_name = trim((string) ($_POST['mailFromName'] ?? ''));
        $db_host = trim((string) ($_POST['dbHost'] ?? ''));
        $db_port = trim((string) ($_POST['dbPort'] ?? ''));
        $db_database = trim((string) ($_POST['dbDatabase'] ?? ''));
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

        $runtime_username = $db_database;
        $runtime_password = bin2hex(random_bytes(24));

        if ($errors === []) {
            try {
                mysqli_query($admin_connection, '
CREATE DATABASE IF NOT EXISTS `' . $db_database . '`
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
');
                mysqli_select_db($admin_connection, $db_database);

                $escaped_password = mysqli_real_escape_string($admin_connection, $runtime_password);

                // CREATE USER/GRANT can't go through mysqli_prepare - account-management
                // statements aren't supported by the prepared-statement protocol on most
                // server versions - so the (self-generated, never user-supplied) password
                // is escaped and interpolated directly instead of bound as a placeholder.
                // Scoped to host '%' rather than $db_host - $db_host is the address the app
                // connects to the server as, not necessarily what the server resolves the
                // connecting client back to for grant-matching (e.g. a loopback TCP
                // connection can resolve to 'localhost' regardless of the host string used
                // to reach it) - matches how the existing runtime account is already scoped.
                mysqli_query($admin_connection, '
CREATE USER IF NOT EXISTS \'' . $runtime_username . '\'@\'%\'
    IDENTIFIED BY \'' . $escaped_password . '\'
');
                mysqli_query($admin_connection, '
GRANT SELECT, INSERT, UPDATE, DELETE
    ON `' . $db_database . '`.*
    TO \'' . $runtime_username . '\'@\'%\'
');

                SchemaInstaller::createTables($admin_connection, SchemaInstaller::missingTables($admin_connection));

                mysqli_close($admin_connection);
            } catch (\mysqli_sql_exception $exception) {
                $errors[] = 'Database setup failed: ' . $exception -> getMessage();
            } catch (\RuntimeException $exception) {
                $errors[] = $exception -> getMessage();
            }
        }

        if ($errors === []) {
            $env_contents = implode("\n", [
                'DB_HOST=' . $db_host,
                'DB_PORT=' . $db_port,
                'DB_DATABASE=' . $db_database,
                'DB_USERNAME=' . $runtime_username,
                'DB_PASSWORD=' . $runtime_password,
                'MAIL_FROM_ADDRESS=' . $mail_from_address,
                'MAIL_FROM_NAME=' . $mail_from_name,
                'SITE_URL=' . $site_url,
                'SITE_TITLE=' . $site_title,
            ]) . "\n";

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

    $page -> addContents(new Notice('Setup finished. The project root was made web-server-writable so this step could write .env - now that it\'s done, run `chmod 755 ' . realpath(__DIR__ . '/..') . '` on the server to restore its normal permissions.'));
    $page -> addContents(new Notice('Then reload this page and sign up to create the administrator account.'));
} elseif ($environment_errors !== []) {
    $page = Page::create('Set Up');

    $page -> addContents(new ErrorList($environment_errors));
} else {
    $page = Page::create('Set Up');

    if ($errors !== []) {
        $page -> addContents(new ErrorList($errors));
    }

    $page -> addContents(new SetupForm());
}

$page -> send();
