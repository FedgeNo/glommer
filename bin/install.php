<?php

declare(strict_types=1);

/**
 * Interactive installer / requirements checker: `php bin/install.php`.
 *
 * On a fresh server this walks the entire install: it verifies every
 * environment prerequisite (offering to set up the WebSocket server's
 * systemd service itself if that's what's missing), prompts for the site
 * and database settings, provisions the database + a least-privilege
 * runtime account + the schema, and writes .env - the same steps the web
 * setup wizard performs, minus the browser.
 *
 * On an existing install it re-verifies everything, creates any missing
 * tables, and detects schema drift (columns/indexes/foreign keys that
 * schema.sql defines but the live tables lack - e.g. after upgrading),
 * offering to apply the exact ALTER statements needed.
 *
 * Every prompt is skipped when stdin isn't a terminal (CI, piped runs) -
 * in that mode it reports what it would have asked about and exits
 * non-zero, so scripts can't hang on a question nobody will answer.
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

// ---------- Output helpers ----------

function supports_color(): bool
{
    return function_exists('stream_isatty') && stream_isatty(STDOUT);
}

function color(string $text, string $code): string
{
    return supports_color() ? "\033[" . $code . 'm' . $text . "\033[0m" : $text;
}

function ok(string $message): void
{
    echo color('[ OK ]', '32') . ' ' . $message . "\n";
}

function warn(string $message): void
{
    echo color('[WARN]', '33') . ' ' . $message . "\n";
}

function fail_line(string $message): void
{
    echo color('[FAIL]', '31') . ' ' . $message . "\n";
}

function fail(string $message): never
{
    fail_line($message);
    exit(1);
}

function heading(string $text): void
{
    echo "\n" . color($text, '1') . "\n";
}

// ---------- Prompt helpers ----------

function is_interactive(): bool
{
    return function_exists('stream_isatty') && stream_isatty(STDIN);
}

/**
 * Prompts until the answer passes $validate (which returns an error message
 * or null). An empty answer takes $default when one is given.
 */
function prompt(string $label, ?string $default = null, ?callable $validate = null): string
{
    while (true) {
        echo $label . ($default !== null ? ' [' . $default . ']' : '') . ': ';
        $line = fgets(STDIN);

        if ($line === false) {
            fail('stdin closed - aborting.');
        }

        $answer = trim($line);

        if ($answer === '' && $default !== null) {
            $answer = $default;
        }

        $error = $validate !== null ? $validate($answer) : null;

        if ($error === null && $answer !== '') {
            return $answer;
        }

        fail_line($error ?? 'A value is required.');
    }
}

/**
 * Like prompt(), but with terminal echo turned off (when stty is available)
 * so the password isn't displayed or left in the scrollback.
 */
function prompt_hidden(string $label): string
{
    $stty_available = trim((string) shell_exec('command -v stty 2>/dev/null')) !== '';

    if ($stty_available) {
        shell_exec('stty -echo');
    }

    echo $label . ': ';
    $line = fgets(STDIN);

    if ($stty_available) {
        shell_exec('stty echo');
        echo "\n";
    }

    if ($line === false) {
        fail('stdin closed - aborting.');
    }

    return trim($line);
}

function confirm(string $question): bool
{
    echo $question . ' [y/N]: ';
    $line = fgets(STDIN);

    return $line !== false && strtolower(trim($line)) === 'y';
}

// ---------- WebSocket systemd service offer ----------

/**
 * When the WebSocket server check is what failed, the fix is usually "set
 * up the service" - so offer to do exactly that: write the user-level
 * systemd unit (no root needed), enable it, start it.
 */
function offer_websocket_service(): bool
{
    $systemctl_available = trim((string) shell_exec('command -v systemctl 2>/dev/null')) !== '';

    if (!$systemctl_available) {
        return false;
    }

    $project_root = dirname(__DIR__);
    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-websocket.service';

    echo "\nThe WebSocket server isn't running. It can be installed as a user-level\n";
    echo "systemd service (no root needed) at " . $unit_path . "\n";

    if (!confirm('Create and start it now?')) {
        return false;
    }

    $unit_contents = implode("\n", [
        '[Unit]',
        'Description=Glommer WebSocket server',
        'After=network.target',
        '',
        '[Service]',
        'ExecStart=' . PHP_BINARY . ' ' . $project_root . '/bin/websocket-server.php',
        'Restart=always',
        'RestartSec=2',
        'WorkingDirectory=' . $project_root,
        '',
        '[Install]',
        'WantedBy=default.target',
    ]) . "\n";

    if (!is_dir(dirname($unit_path)) && !@mkdir(dirname($unit_path), 0755, true)) {
        fail_line('Could not create ' . dirname($unit_path) . ' - create the unit manually (see README.md).');

        return false;
    }

    if (file_put_contents($unit_path, $unit_contents) === false) {
        fail_line('Could not write ' . $unit_path . ' - create the unit manually (see README.md).');

        return false;
    }

    shell_exec('systemctl --user daemon-reload 2>&1');
    $enable_output = (string) shell_exec('systemctl --user enable --now glommer-websocket.service 2>&1');

    // Give the daemon a moment to bind its ports before re-checking.
    sleep(1);

    $status = trim((string) shell_exec('systemctl --user is-active glommer-websocket.service 2>/dev/null'));

    if ($status !== 'active') {
        fail_line('The service was written but did not start (' . trim($enable_output) . '). Check: systemctl --user status glommer-websocket');

        return false;
    }

    ok('WebSocket service installed and started (' . $unit_path . ')');
    echo 'Note: user services stop at logout unless lingering is enabled - if this account is not' . "\n";
    echo 'always logged in, run: loginctl enable-linger ' . (get_current_user() ?: '<user>') . "\n";

    return true;
}

// ---------- 1. Environment ----------

heading('Environment');

$run_environment_checks = function (): array {
    $failures = [];

    foreach (EnvironmentChecker::checks() as $name => $result) {
        if ($result['ok']) {
            ok($result['message']);
        } else {
            fail_line($result['message']);
            $failures[$name] = $result['message'];
        }
    }

    return $failures;
};

$environment_failures = $run_environment_checks();

// A missing WebSocket server is the one environment failure this script can
// fix itself - offer to, then re-run just that check.
if (isset($environment_failures['WebSocket server']) && is_interactive() && offer_websocket_service()) {
    $recheck = EnvironmentChecker::checks()['WebSocket server'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['WebSocket server']);
    } else {
        fail_line($recheck['message']);
    }
}

if ($environment_failures !== []) {
    echo "\n" . count($environment_failures) . ' environment problem(s) listed above - fix them and re-run this script.' . "\n";
    exit(1);
}

// ---------- 2. Configuration (.env) ----------

heading('Configuration');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

if (!is_file(__DIR__ . '/../.env')) {
    if (!is_interactive()) {
        fail('No .env file found in the project root. Run this script in a terminal to be walked through creating one, use the web setup wizard (visit the site in a browser), or create it by hand (copy .env.example; see src/config.php for every key and its default).');
    }

    echo "No .env found - let's create one. You'll need MySQL admin credentials (an account\n";
    echo "with CREATE/CREATE USER/GRANT privileges, e.g. root); they're used once to provision\n";
    echo "the database and are never stored.\n\n";

    $site_url = prompt('Site URL (e.g. https://example.com)', null, function (string $value): ?string {
        return filter_var($value, FILTER_VALIDATE_URL) === false ? 'That is not a valid URL.' : null;
    });
    $site_title = prompt('Site title', 'Glommer');
    $mail_from_address = prompt('Mail "from" address', null, function (string $value): ?string {
        return filter_var($value, FILTER_VALIDATE_EMAIL) === false ? 'That is not a valid email address.' : null;
    });
    $mail_from_name = prompt('Mail "from" name', $site_title);
    $db_host = prompt('Database host', '127.0.0.1', function (string $value): ?string {
        return preg_match('/^[A-Za-z0-9_.:-]+$/', $value) !== 1 ? 'The host contains invalid characters.' : null;
    });
    $db_port = prompt('Database port', '3306', function (string $value): ?string {
        return (preg_match('/^[0-9]{1,5}$/', $value) !== 1 || (int) $value < 1 || (int) $value > 65535) ? 'The port must be a number between 1 and 65535.' : null;
    });
    $db_database = prompt('Database name', 'glommer', function (string $value): ?string {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/', $value) !== 1 ? 'The name may only contain letters, numbers, and underscores.' : null;
    });

    $admin_connection = null;

    while ($admin_connection === null) {
        $admin_username = prompt('Database admin username', 'root');
        $admin_password = prompt_hidden('Database admin password');

        try {
            $admin_connection = mysqli_connect($db_host, $admin_username, $admin_password, null, (int) $db_port);
        } catch (\mysqli_sql_exception $exception) {
            fail_line('Could not connect: ' . $exception -> getMessage() . ' - try again.');
        }
    }

    try {
        $runtime_account = Installer::provisionDatabase($admin_connection, $db_database);
    } catch (\mysqli_sql_exception $exception) {
        fail('Database setup failed: ' . $exception -> getMessage());
    }

    mysqli_close($admin_connection);
    ok('database, runtime account, and schema provisioned');

    $ws_secret = bin2hex(random_bytes(32));

    // config.php's WS defaults are what the (already-running, per the
    // environment check above) daemon is currently using - carry them
    // forward so the values recorded in .env match reality.
    $existing_config = require __DIR__ . '/../src/config.php';

    $env_values = [
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
    ];

    if (file_put_contents(__DIR__ . '/../.env', Installer::envContents($env_values)) === false) {
        fail('Could not write .env - check that ' . realpath(__DIR__ . '/..') . ' is writable by this user.');
    }

    ok('.env written');

    // Env caches on first read and getenv() keeps serving stale values, so
    // push the values just written into the running process directly - the
    // config.php require below then resolves them without a restart.
    foreach ($env_values as $key => $value) {
        putenv($key . '=' . $value);
    }
}

$config = require __DIR__ . '/../src/config.php';

if ($config['siteURL'] === 'https://example.com') {
    fail('SITE_URL is not set in .env - every generated link would point at the https://example.com placeholder.');
}

if ($config['WSSecret'] === 'change-me') {
    fail('WS_SECRET is still the .env.example placeholder - anyone who reads that public default can forge WebSocket auth tokens for any user. Set it to a real random value, e.g.: php -r \'echo bin2hex(random_bytes(32));\'');
}

ok('.env present, SITE_URL and WS_SECRET configured (' . $config['siteURL'] . ')');

// ---------- 3. Database connection ----------

heading('Database');

try {
    $mysqli = mysqli_connect($config['host'], $config['username'], $config['password'], $config['database'], $config['port']);
} catch (\mysqli_sql_exception $exception) {
    fail('Could not connect to the database with the credentials in .env: ' . $exception -> getMessage());
}

ok('database connection works (' . $config['username'] . '@' . $config['host'] . ':' . $config['port'] . '/' . $config['database'] . ')');

// ---------- 4. Schema ----------

/**
 * The app's own runtime DB user is intentionally least-privilege and can't
 * CREATE/ALTER, so schema work needs a separately-supplied admin connection
 * - from DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD when set, otherwise prompted
 * for interactively. Only requested when there's actual schema work to do.
 */
function admin_connection(array $config, string $needed_for): ?\mysqli
{
    $admin_username = Env::get('DB_ADMIN_USERNAME');
    $admin_password = Env::get('DB_ADMIN_PASSWORD');

    if ($admin_username !== null && $admin_password !== null) {
        try {
            return mysqli_connect($config['host'], $admin_username, $admin_password, $config['database'], $config['port']);
        } catch (\mysqli_sql_exception $exception) {
            fail('Could not connect with the DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD credentials: ' . $exception -> getMessage());
        }
    }

    if (!is_interactive()) {
        return null;
    }

    echo "\nMySQL admin credentials are needed to " . $needed_for . " (used once, never stored).\n";

    while (true) {
        $admin_username = prompt('Database admin username', 'root');
        $admin_password = prompt_hidden('Database admin password');

        try {
            return mysqli_connect($config['host'], $admin_username, $admin_password, $config['database'], $config['port']);
        } catch (\mysqli_sql_exception $exception) {
            fail_line('Could not connect: ' . $exception -> getMessage() . ' - try again.');
        }
    }
}

try {
    $missing_statements = SchemaInstaller::missingTables($mysqli);
} catch (\RuntimeException $exception) {
    fail($exception -> getMessage());
}

if ($missing_statements === []) {
    ok('all tables already exist');
} else {
    $admin_mysqli = admin_connection($config, 'create ' . count($missing_statements) . ' missing table(s): ' . implode(', ', array_keys($missing_statements)));

    if ($admin_mysqli === null) {
        fail(
            count($missing_statements) . ' table(s) are missing ('
            . implode(', ', array_keys($missing_statements)) . '). '
            . 'Create them either by running `mysql -u <admin> -p ' . $config['database'] . ' < schema.sql` yourself, '
            . 'or by setting DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD (an account with CREATE privileges) before re-running this script.'
        );
    }

    try {
        SchemaInstaller::createTables($admin_mysqli, $missing_statements);
    } catch (\mysqli_sql_exception $exception) {
        fail('Failed to create missing table(s): ' . $exception -> getMessage());
    }

    mysqli_close($admin_mysqli);

    ok('created ' . count($missing_statements) . ' missing table(s): ' . implode(', ', array_keys($missing_statements)));
}

// ---------- 5. Schema drift ----------

$drift = SchemaInstaller::missingDefinitions($mysqli);

if ($drift === []) {
    ok('existing tables match schema.sql (no missing columns, indexes, or foreign keys)');
} else {
    $labels = [];

    foreach ($drift as $table => $alters) {
        foreach (array_keys($alters) as $label) {
            $labels[] = $table . ': ' . $label;
        }
    }

    warn('existing tables are missing ' . count($labels) . ' definition(s) from schema.sql:');

    foreach ($labels as $label) {
        echo '       - ' . $label . "\n";
    }

    $apply = false;
    $admin_mysqli = null;

    if (is_interactive() || (Env::get('DB_ADMIN_USERNAME') !== null && Env::get('DB_ADMIN_PASSWORD') !== null)) {
        if (!is_interactive() || confirm('Apply the ALTER statements to bring them up to date?')) {
            $admin_mysqli = admin_connection($config, 'apply the missing definitions');
            $apply = $admin_mysqli !== null;
        }
    }

    if ($apply) {
        $failed = 0;

        foreach ($drift as $table => $alters) {
            foreach ($alters as $label => $alter) {
                try {
                    mysqli_query($admin_mysqli, $alter);
                    ok('applied ' . $table . ': ' . $label);
                } catch (\mysqli_sql_exception $exception) {
                    $failed++;
                    fail_line('could not apply ' . $table . ': ' . $label . ' - ' . $exception -> getMessage());
                }
            }
        }

        mysqli_close($admin_mysqli);

        if ($failed > 0) {
            fail($failed . ' ALTER(s) failed - see above. The statements come straight from schema.sql; apply them manually once the cause is fixed.');
        }
    } else {
        fail_line('not applied - the app may misbehave until the schema is brought up to date. The exact statements:');

        foreach ($drift as $alters) {
            foreach ($alters as $alter) {
                echo '       ' . $alter . ";\n";
            }
        }

        echo "\nApply them as a MySQL admin (or re-run this script interactively / with DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD set) and re-run.\n";
        exit(1);
    }
}

// ---------- 6. Maintenance ----------

// Idempotent data upkeep defined alongside the DDL in schema.sql (currently
// the friendCount recompute) - run on the runtime connection, which has the
// UPDATE it needs, now that the tables are known-good.
SchemaInstaller::runMaintenance($mysqli);
ok('schema.sql maintenance applied (denormalized counts recomputed)');

// ---------- Done ----------

echo "\n" . color('All checks passed.', '1;32') . "\n\n";
echo "Next steps:\n";
echo "  1. If .env was just created, restart the WebSocket server so it picks up the fresh\n";
echo "     WS_SECRET: systemctl --user restart glommer-websocket\n";
echo "  2. Visit " . $config['siteURL'] . " and sign up - the first account created becomes\n";
echo "     the site's administrator.\n";
