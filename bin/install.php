<?php

declare(strict_types=1);

/**
 * Install-requirements check: `php bin/install.php` on a fresh server.
 * Verifies everything the app needs from its environment, in dependency
 * order, stopping with an error at the first obstacle that would prevent
 * the system from working properly - fix it and re-run until the script
 * passes. (Schema creation and the rest of the install process will be
 * added to this script later.)
 */

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

function fail(string $message): never
{
    echo 'ERROR: ' . $message . "\n";
    exit(1);
}

function ok(string $message): void
{
    echo 'OK: ' . $message . "\n";
}

// ---------- Environment ----------

foreach (EnvironmentChecker::checks() as $result) {
    if (!$result['ok']) {
        fail($result['message']);
    }

    ok($result['message']);
}

// ---------- Configuration ----------

if (!is_file(__DIR__ . '/../.env')) {
    fail('No .env file found in the project root. Create one defining at least SITE_URL, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD (see src/config.php for every key and its default).');
}

$config = require __DIR__ . '/../src/config.php';

if ($config['siteURL'] === 'https://example.com') {
    fail('SITE_URL is not set in .env - every generated link would point at the https://example.com placeholder.');
}

ok('.env present, SITE_URL configured (' . $config['siteURL'] . ')');

// ---------- Database connection ----------

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $mysqli = mysqli_connect($config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
} catch (\mysqli_sql_exception $exception) {
    fail('Could not connect to the database with the credentials in .env: ' . $exception -> getMessage());
}

ok('database connection works (' . $config['username'] . '@' . $config['host'] . ':' . $config['port'] . '/' . $config['database'] . ')');

// ---------- Schema ----------

try {
    $missing_statements = SchemaInstaller::missingTables($mysqli);
} catch (\RuntimeException $exception) {
    fail($exception -> getMessage());
}

if ($missing_statements === []) {
    ok('all tables already exist');
} else {
    // The app's own runtime DB user is intentionally least-privilege (see
    // schema.sql's own header) and normally can't CREATE TABLE, so this step
    // needs a separately-supplied admin connection - only requested when
    // there's actually a missing table to create.
    $admin_username = Env::get('DB_ADMIN_USERNAME');
    $admin_password = Env::get('DB_ADMIN_PASSWORD');

    if ($admin_username === null || $admin_password === null) {
        fail(
            count($missing_statements) . ' table(s) are missing ('
            . implode(', ', array_keys($missing_statements)) . '). '
            . 'Create them either by running `mysql -u <admin> -p ' . $config['database'] . ' < schema.sql` yourself, '
            . 'or by setting DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD (an account with CREATE privileges) before re-running this script.'
        );
    }

    try {
        $admin_mysqli = mysqli_connect($config['host'], $admin_username, $admin_password, $config['database'], $config['port']);
    } catch (\mysqli_sql_exception $exception) {
        fail('Could not connect with the DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD credentials: ' . $exception -> getMessage());
    }

    try {
        SchemaInstaller::createTables($admin_mysqli, $missing_statements);
    } catch (\mysqli_sql_exception $exception) {
        fail('Failed to create missing table(s): ' . $exception -> getMessage());
    }

    mysqli_close($admin_mysqli);

    ok('created ' . count($missing_statements) . ' missing table(s): ' . implode(', ', array_keys($missing_statements)));
}

echo "\nAll checks passed.\n";
