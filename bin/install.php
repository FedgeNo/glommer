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

    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-websocket.service';

    echo "\nThe WebSocket server isn't running. It can be installed as a user-level\n";
    echo "systemd service (no root needed) at " . $unit_path . "\n";

    if (!confirm('Create and start it now?')) {
        return false;
    }

    return write_and_enable_websocket_service();
}

/**
 * Writes (if missing) and enables the user-level systemd service that runs
 * bin/websocket-server.php, plus the lingering it needs to survive logout
 * and reboot. Shared by offer_websocket_service() (the daemon isn't even
 * reachable) and offer_enable_websocket_service() (the daemon works right
 * now - e.g. started manually - but isn't enabled/lingering isn't set, so it
 * won't survive a restart). Idempotent: re-writing identical unit contents
 * and re-enabling an already-enabled service are both no-ops.
 */
function write_and_enable_websocket_service(): bool
{
    $systemctl_available = trim((string) shell_exec('command -v systemctl 2>/dev/null')) !== '';

    if (!$systemctl_available) {
        warn('systemctl not found - run bin/websocket-server.php under your own process manager. See README.md.');

        return false;
    }

    $project_root = dirname(__DIR__);
    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-websocket.service';

    $unit_contents = implode("\n", [
        '[Unit]',
        'Description=Glommer WebSocket server',
        'After=network.target',
        '',
        '[Service]',
        // Quote the binary and script path - systemd splits ExecStart on
        // whitespace, so an install path or PHP binary path containing a
        // space would otherwise break the command into the wrong arguments.
        'ExecStart="' . PHP_BINARY . '" "' . $project_root . '/bin/websocket-server.php"',
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

    // A user-level service only keeps running after the admin logs out (and
    // only starts on boot) if that user has "lingering" enabled - otherwise
    // the daemon dies the moment whoever ran the installer disconnects, which
    // is exactly the headless-server case. Enable it now (allowed for your own
    // user without root on most systems) so the daemon truly runs unattended.
    $user = trim((string) shell_exec('id -un 2>/dev/null'));

    if ($user === '') {
        $user = get_current_user() ?: (string) getenv('USER');
    }

    shell_exec('loginctl enable-linger ' . escapeshellarg($user) . ' 2>&1');
    $linger_output = strtolower(trim((string) shell_exec('loginctl show-user ' . escapeshellarg($user) . ' --property=Linger 2>/dev/null')));

    if (str_contains($linger_output, 'yes')) {
        ok('Lingering enabled for ' . $user . ' - the daemon keeps running after logout and starts on boot.');
    } else {
        warn('Could not enable lingering for ' . $user . ' automatically. The daemon will stop when this user');
        echo '       logs out (and won\'t start on boot) until you run: sudo loginctl enable-linger ' . $user . "\n";
    }

    return true;
}

/**
 * When the WebSocket service persistence check is what failed - the daemon
 * is reachable right now (satisfying the separate reachability check) but
 * isn't enabled/lingering isn't set, e.g. it was started manually - offer to
 * fix it directly.
 */
function offer_enable_websocket_service(): bool
{
    echo "\nThe WebSocket daemon is reachable right now, but glommer-websocket.service isn't\n";
    echo "enabled (or lingering isn't) - it won't survive a restart or reboot as-is.\n";

    if (!confirm('Set it up now?')) {
        return false;
    }

    return write_and_enable_websocket_service();
}

/**
 * When the Backups check is what failed, fix it completely: run one backup
 * now (proving the mechanism actually works), then install a recurring
 * user-level systemd timer (no root needed) so it keeps being true -
 * mirroring offer_websocket_service() above, not just a one-off run.
 */
function offer_first_backup(): bool
{
    echo "\nNo backup has ever completed.\n";

    if (!confirm('Run php bin/backup.php now to create one and prove the mechanism works?')) {
        return false;
    }

    passthru(PHP_BINARY . ' ' . escapeshellarg(__DIR__ . '/backup.php'), $exit_code);

    if ($exit_code !== 0) {
        return false;
    }

    echo "\nIt can also run automatically every night via a user-level systemd timer (no root needed).\n";

    if (!confirm('Set that up now too?')) {
        warn('Not scheduled - run php bin/backup.php manually on some schedule of your own. See README.md\'s Backups section.');

        return true;
    }

    write_and_enable_backup_timer();

    return true;
}

/**
 * Writes (if missing) and enables the user-level systemd timer that runs
 * php bin/backup.php nightly, plus the lingering it needs to survive logout
 * and reboot. Shared by offer_first_backup() (right after proving a manual
 * backup works) and offer_enable_backup_timer() (the timer's own persistence
 * check can fail independently - e.g. someone ran bin/backup.php by hand
 * once, satisfying the "a backup exists" check, without ever scheduling
 * anything).
 */
function write_and_enable_backup_timer(): void
{
    $systemctl_available = trim((string) shell_exec('command -v systemctl 2>/dev/null')) !== '';

    if (!$systemctl_available) {
        warn('systemctl not found - set up a recurring backup yourself (cron or otherwise). See README.md\'s Backups section.');

        return;
    }

    $home = ($_SERVER['HOME'] ?? getenv('HOME'));
    $service_path = $home . '/.config/systemd/user/glommer-backup.service';
    $timer_path = $home . '/.config/systemd/user/glommer-backup.timer';

    $service_contents = implode("\n", [
        '[Unit]',
        'Description=Glommer backup',
        '',
        '[Service]',
        'Type=oneshot',
        'ExecStart=' . PHP_BINARY . ' ' . __DIR__ . '/backup.php',
    ]) . "\n";

    $timer_contents = implode("\n", [
        '[Unit]',
        'Description=Nightly Glommer backup',
        '',
        '[Timer]',
        'OnCalendar=*-*-* 04:00:00',
        'Persistent=true',
        '',
        '[Install]',
        'WantedBy=timers.target',
    ]) . "\n";

    if (!is_dir(dirname($service_path)) && !@mkdir(dirname($service_path), 0755, true)) {
        fail_line('Could not create ' . dirname($service_path) . ' - create the units manually (see README.md).');

        return;
    }

    if (file_put_contents($service_path, $service_contents) === false || file_put_contents($timer_path, $timer_contents) === false) {
        fail_line('Could not write the systemd units - create them manually (see README.md).');

        return;
    }

    shell_exec('systemctl --user daemon-reload 2>&1');
    $enable_output = (string) shell_exec('systemctl --user enable --now glommer-backup.timer 2>&1');

    $status = trim((string) shell_exec('systemctl --user is-enabled glommer-backup.timer 2>/dev/null'));

    if ($status !== 'enabled') {
        fail_line('The timer was written but is not enabled (' . trim($enable_output) . '). Check: systemctl --user status glommer-backup.timer');

        return;
    }

    ok('Nightly backup timer installed and enabled (' . $timer_path . ')');

    // A user-level timer only survives logout/reboot with lingering enabled.
    $user = trim((string) shell_exec('id -un 2>/dev/null'));

    if ($user === '') {
        $user = get_current_user() ?: (string) getenv('USER');
    }

    shell_exec('loginctl enable-linger ' . escapeshellarg($user) . ' 2>&1');
    $linger_output = strtolower(trim((string) shell_exec('loginctl show-user ' . escapeshellarg($user) . ' --property=Linger 2>/dev/null')));

    if (str_contains($linger_output, 'yes')) {
        ok('Lingering enabled for ' . $user . ' - the timer keeps firing after logout and across reboots.');
    } else {
        warn('Could not enable lingering for ' . $user . ' automatically. The timer won\'t fire after logout');
        echo '       (or survive a reboot) until you run: sudo loginctl enable-linger ' . $user . "\n";
    }
}

/**
 * When the Backup timer persistence check is what failed - a backup exists
 * (satisfying the separate Backups check) but nothing's scheduled to run one
 * again, or the timer/lingering fell out of enabled state some other way -
 * offer to fix it directly. write_and_enable_backup_timer() is itself
 * idempotent (re-writing identical unit files and re-enabling an
 * already-enabled timer are both no-ops), so this is safe to run whether or
 * not the units already exist.
 */
function offer_enable_backup_timer(): bool
{
    echo "\nThe nightly backup timer isn't enabled (or lingering isn't, so it wouldn't survive logout/reboot even if it were).\n";

    if (!confirm('Set it up now?')) {
        return false;
    }

    write_and_enable_backup_timer();

    return true;
}

/**
 * When SITE_URL is https (required) but the WebSocket daemon has no TLS
 * configured, a browser on the site's own https pages silently refuses to
 * open a plain ws:// connection at all (mixed active content - no visible
 * error, live notifications/messaging just stop working). Offer to generate
 * a certificate with mkcert (if available) and wire it in.
 */
function offer_websocket_tls(string $host): bool
{
    $mkcert_available = trim((string) shell_exec('command -v mkcert 2>/dev/null')) !== '';

    if (!$mkcert_available) {
        return false;
    }

    $cert_dir = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.local/share/glommer-certs';
    $cert_path = $cert_dir . '/' . $host . '.pem';
    $key_path = $cert_dir . '/' . $host . '-key.pem';

    echo "\nThe WebSocket daemon has no TLS certificate configured. mkcert is available -\n";
    echo "it can generate one for " . $host . " at " . $cert_dir . "\n";
    echo "(Browsers only trust it without a warning if \"mkcert -install\" has been run\n";
    echo "on this machine - a one-time step, needs sudo the first time, separate from this.)\n";

    if (!confirm('Generate a certificate now and configure it?')) {
        return false;
    }

    if (!is_dir($cert_dir) && !@mkdir($cert_dir, 0700, true)) {
        fail_line('Could not create ' . $cert_dir . ' - create it manually and set WS_TLS_CERT/WS_TLS_KEY in .env.');

        return false;
    }

    exec('mkcert -cert-file ' . escapeshellarg($cert_path) . ' -key-file ' . escapeshellarg($key_path) . ' ' . escapeshellarg($host) . ' 2>&1', $mkcert_output, $mkcert_exit);

    if ($mkcert_exit !== 0 || !is_file($cert_path) || !is_file($key_path)) {
        fail_line('mkcert failed: ' . implode(' ', $mkcert_output));

        return false;
    }

    if (!EnvironmentChecker::webSocketCertificateAndKeyMatch($cert_path, $key_path)) {
        fail_line('mkcert reported success, but the generated certificate and key don\'t actually match - not using them. Generate a certificate manually (see README.md\'s HTTPS section) and set WS_TLS_CERT/WS_TLS_KEY in .env.');

        return false;
    }

    $env_path = __DIR__ . '/../.env';
    $env_contents = (string) file_get_contents($env_path);
    // preg_replace_callback, not preg_replace: the callback's return value is
    // inserted literally, with no $1/\1-style backreference interpretation of
    // the replacement text - a plain preg_replace() would mangle a cert/key
    // path containing a literal "$" followed by digits (e.g. an un-expanded
    // "$HOME" left in the path).
    $env_contents = preg_replace_callback('/^WS_TLS_CERT=.*$/m', fn () => 'WS_TLS_CERT=' . $cert_path, $env_contents, -1, $cert_replaced);
    $env_contents = preg_replace_callback('/^WS_TLS_KEY=.*$/m', fn () => 'WS_TLS_KEY=' . $key_path, $env_contents, -1, $key_replaced);

    if ($cert_replaced !== 1 || $key_replaced !== 1) {
        fail_line('.env has no WS_TLS_CERT/WS_TLS_KEY lines to update - add these manually:');
        echo '       WS_TLS_CERT=' . $cert_path . "\n";
        echo '       WS_TLS_KEY=' . $key_path . "\n";

        return false;
    }

    if (file_put_contents($env_path, $env_contents) === false) {
        fail_line('Could not write .env.');

        return false;
    }

    putenv('WS_TLS_CERT=' . $cert_path);
    putenv('WS_TLS_KEY=' . $key_path);

    ok('.env updated with WS_TLS_CERT/WS_TLS_KEY');

    $systemctl_available = trim((string) shell_exec('command -v systemctl 2>/dev/null')) !== '';

    if ($systemctl_available) {
        shell_exec('systemctl --user restart glommer-websocket.service 2>&1');
        sleep(1);

        $status = trim((string) shell_exec('systemctl --user is-active glommer-websocket.service 2>/dev/null'));

        if ($status === 'active') {
            ok('WebSocket daemon restarted with the new certificate');
        } else {
            warn('Could not confirm the daemon restarted - check: systemctl --user status glommer-websocket');
        }
    } else {
        warn('Restart the WebSocket daemon manually to pick up the new certificate.');
    }

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

// Same explicit, named non-interactive opt-in pattern as
// SERVERNAME_CONFIRMED/BACKUP_TIMER_CONFIRMED - these two WebSocket offers
// are purely mechanical (write a unit file, enable it, set lingering; both
// idempotent) with nothing to blindly trust, unlike ServerName, so a named
// opt-in unlocking them without a TTY is safe the same way it is for backups.
$websocket_non_interactive_ok = Env::get('WEBSOCKET_SERVICE_CONFIRMED', '') === '1';

// A missing WebSocket server is one environment failure this script can fix
// itself - offer to, then re-run just that check.
if (isset($environment_failures['WebSocket server']) && (is_interactive() || $websocket_non_interactive_ok) && offer_websocket_service()) {
    $recheck = EnvironmentChecker::checks()['WebSocket server'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['WebSocket server']);
    } else {
        fail_line($recheck['message']);
    }

    // offer_websocket_service() may have set persistence up too as part of
    // the same flow - re-check it alongside so one "y" answer clears both.
    $persistence_recheck = EnvironmentChecker::checks()['WebSocket service persistence'];

    if ($persistence_recheck['ok']) {
        unset($environment_failures['WebSocket service persistence']);
    }
}

// Separately: the daemon can be reachable right now (satisfying the check
// above) without being enabled to survive a restart or reboot - e.g. it was
// started manually. Offer to fix that specifically too.
if (isset($environment_failures['WebSocket service persistence']) && (is_interactive() || $websocket_non_interactive_ok) && offer_enable_websocket_service()) {
    $recheck = EnvironmentChecker::checks()['WebSocket service persistence'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['WebSocket service persistence']);
    } else {
        fail_line($recheck['message']);
    }
}

// Likewise the one environment failure this script can fix itself by running
// a real backup on the spot. BACKUP_TIMER_CONFIRMED=1 is the same explicit,
// named non-interactive opt-in as SERVERNAME_CONFIRMED below - it unlocks
// offer_first_backup() without a TTY, but its confirm() calls still read real
// answers off stdin (a pipe works fine for that; only stream_isatty() cares
// whether it's a real terminal), so this isn't a blind bypass - an actual "y"
// still has to arrive for each step.
$backups_non_interactive_ok = Env::get('BACKUP_TIMER_CONFIRMED', '') === '1';

if (isset($environment_failures['Backups']) && (is_interactive() || $backups_non_interactive_ok) && offer_first_backup()) {
    $recheck = EnvironmentChecker::checks()['Backups'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['Backups']);
    } else {
        fail_line($recheck['message']);
    }

    // offer_first_backup() may have set the timer up too as part of the same
    // flow - re-check this one alongside it so a single "y" answer there
    // clears both failures instead of needing a second pass.
    $timer_recheck = EnvironmentChecker::checks()['Backup timer persistence'];

    if ($timer_recheck['ok']) {
        unset($environment_failures['Backup timer persistence']);
    }
}

// Separately: the backup mechanism can work (satisfying the check above)
// without anything actually being scheduled to run it again - e.g. someone
// ran bin/backup.php by hand once. Offer to fix that specifically too.
if (isset($environment_failures['Backup timer persistence']) && (is_interactive() || $backups_non_interactive_ok) && offer_enable_backup_timer()) {
    $recheck = EnvironmentChecker::checks()['Backup timer persistence'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['Backup timer persistence']);
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

    // .env holds DB_PASSWORD and WS_SECRET. Create it 0600 from the start
    // (umask 0077 → new file mode 0600) so there's no window where it lands
    // group/world-readable before a later chmod - file_put_contents would
    // otherwise create it at the default umask first.
    $env_path = __DIR__ . '/../.env';
    $previous_umask = umask(0077);
    $env_written = file_put_contents($env_path, Installer::envContents($env_values));
    umask($previous_umask);

    if ($env_written === false) {
        fail('Could not write .env - check that ' . realpath(__DIR__ . '/..') . ' is writable by this user.');
    }

    // Belt-and-suspenders for the re-run case (umask doesn't tighten an
    // already-existing file) - and a failure here is fatal, not swallowed,
    // since a world-readable .env leaks the DB password and WS secret.
    if (!chmod($env_path, 0600)) {
        fail('Wrote .env but could not restrict it to 0600 - it holds DB_PASSWORD and WS_SECRET. Fix manually: chmod 600 ' . realpath($env_path));
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

// HTTPS is a requirement, not a preference: an http:// SITE_URL is a
// dealbreaker. The site itself refuses to serve over plain HTTP (everything
// 301s to https, and an http SITE_URL gets a config-error page), so an
// install without TLS simply doesn't work - get the certificate first.
if (!str_starts_with((string) $config['siteURL'], 'https://')) {
    fail('SITE_URL is ' . $config['siteURL'] . ' - Glommer requires HTTPS and will not serve over plain HTTP. Set up TLS first, then set SITE_URL to the https:// URL. For a real domain use Let\'s Encrypt (certbot --apache -d your.domain); for localhost use a locally-trusted certificate (mkcert) or your distribution\'s self-signed default (Fedora: dnf install mod_ssl). See README.md\'s HTTPS section.');
}

// The check above only proves SITE_URL *says* https:// - it's just a string,
// not proof anything is actually listening with a working certificate. Test
// for real: connect to the site's own hostname over HTTPS and see what
// happens. Not 127.0.0.1 - a VirtualHost setup routes by the Host header
// (SNI for TLS), so the loopback address may not reach this site at all;
// the real hostname is what actually has to work.
$site_host = (string) (parse_url($config['siteURL'], PHP_URL_HOST) ?: 'your-domain');
$site_port = parse_url($config['siteURL'], PHP_URL_PORT);
$server_name_value = $site_host . ($site_port !== null ? ':' . $site_port : '');

$https_serving = EnvironmentChecker::httpsServing($server_name_value);

if ($https_serving === false) {
    fail('SITE_URL is https://... but a real HTTPS connection to ' . $server_name_value . ' failed at the TLS handshake itself - something is listening on the port without actually serving TLS. Check that Apache\'s SSLCertificateFile/SSLCertificateKeyFile are set and mod_ssl is loaded, then re-run.');
} elseif ($https_serving === true) {
    ok('HTTPS confirmed live (a real connection to ' . $server_name_value . ' over TLS succeeded)');
} else {
    warn('Could not confirm HTTPS live by connecting to ' . $server_name_value . ' - inconclusive (DNS may not point here yet, a firewall, or the web server isn\'t up yet). Verify by visiting ' . $config['siteURL'] . ' in a browser.');
}

if ($config['WSSecret'] === null) {
    fail('WS_SECRET is missing or still the .env.example placeholder - a WebSocket secret anyone can read (or an absent one) lets connection tokens be forged for any user. Set it to a real random value, e.g.: php -r \'echo bin2hex(random_bytes(32));\'');
}

ok('.env present, SITE_URL and WS_SECRET configured (' . $config['siteURL'] . ')');

// ---------- HTTPS host-spoofing guard ----------

// By default, Apache builds SERVER_NAME (and mod_rewrite's %{SERVER_NAME}) -
// and hence the target of the HTTPS redirect in .htaccess - from whatever
// Host header the request arrived with, not from a fixed configured name.
// That means anyone can forge a Host header and get 301-redirected to a
// domain of their choosing instead of this site - a phishing/cache-poisoning
// primitive. The fix is two Apache directives that make Apache ignore the
// client-supplied Host header for this purpose:
//   ServerName <host>[:<port>]
//   UseCanonicalName On
// Rather than just ask whether ServerName/UseCanonicalName are set, prove it:
// send a request to the real hostname with a deliberately forged Host header
// and check whether the redirect reflects it. ($site_host/$server_name_value
// were already computed above for the HTTPS-serving check.)
$spoof_test = EnvironmentChecker::hostHeaderSpoofable($site_host);

if ($spoof_test === true) {
    fail('The HTTPS redirect can be spoofed via a forged Host header (confirmed live: a request with a fake Host '
        . 'header got redirected to that same fake host) - anyone can 301 a victim to a domain of their choosing. '
        . 'Set "ServerName ' . $server_name_value . '" and "UseCanonicalName On" in Apache\'s config '
        . '(httpd.conf\'s top level if you\'re not using a <VirtualHost>, or inside the vhost if you are), then '
        . 're-run. See README.md\'s HTTPS section.');
} elseif ($spoof_test === false) {
    ok('ServerName + UseCanonicalName confirmed live (a forged Host header was not reflected in the redirect)');
} else {
    // Couldn't prove it either way (no reachable response, or no redirect to
    // inspect) - fall back to asking, same as before this check existed.
    $server_name_question = 'Could not confirm this live. Have you set "ServerName ' . $server_name_value . '" and '
        . '"UseCanonicalName On" in Apache\'s config (httpd.conf\'s top level if you\'re not using a <VirtualHost>, '
        . 'or inside the vhost if you are)?';

    if (is_interactive()) {
        if (!confirm($server_name_question)) {
            fail('Set "ServerName ' . $server_name_value . '" and "UseCanonicalName On" in Apache\'s config first - '
                . 'required so the HTTPS redirect can\'t be spoofed via a forged Host header. See README.md\'s HTTPS section.');
        }

        ok('ServerName + UseCanonicalName confirmed');
    } elseif (Env::get('SERVERNAME_CONFIRMED', '') === '1') {
        ok('ServerName + UseCanonicalName confirmed (SERVERNAME_CONFIRMED=1)');
    } else {
        fail('Cannot confirm interactively (no terminal), and could not verify it live. Set "ServerName ' . $server_name_value . '" and '
            . '"UseCanonicalName On" in Apache\'s config, then set SERVERNAME_CONFIRMED=1 to continue non-interactively. '
            . 'See README.md\'s HTTPS section.');
    }
}

// ---------- WebSocket TLS ----------

// HTTPS is required site-wide (confirmed above), and a browser on an https
// page silently refuses to open a plain ws:// connection at all (mixed
// active content - no console warning most people would notice, live
// notifications/messaging just stop working). So the WebSocket daemon must
// be configured for TLS too, or the install isn't actually functional.
if ($config['WSTLSCert'] === null || $config['WSTLSKey'] === null) {
    if (!is_interactive() || !offer_websocket_tls($site_host)) {
        fail('WS_TLS_CERT/WS_TLS_KEY are not set in .env, but SITE_URL is https - browsers silently refuse a plain '
            . 'ws:// connection from an https page, so live notifications and messaging would be dead with no '
            . 'visible error. Generate a certificate for ' . $site_host . ' (mkcert is easiest for localhost/dev: '
            . 'mkcert -install && mkcert ' . $site_host . '; for a real domain you can reuse the certificate Apache '
            . 'uses) and set WS_TLS_CERT/WS_TLS_KEY in .env, then restart the WebSocket daemon. See README.md\'s '
            . 'HTTPS section.');
    }

    // Plain require (not require_once), same as every other config reload in
    // this script - re-executes and picks up the WS_TLS_CERT/WS_TLS_KEY
    // offer_websocket_tls() just putenv()'d.
    $config = require __DIR__ . '/../src/config.php';
}

ok('WebSocket TLS configured (' . $config['WSTLSCert'] . ')');

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

// Idempotent DML upkeep defined alongside the DDL in schema.sql (currently
// the friendCount recompute) - run on the runtime connection, which has the
// UPDATE it needs, now that the tables are known-good.
SchemaInstaller::runMaintenance($mysqli);
ok('schema.sql maintenance applied (denormalized counts recomputed)');

// Backfill descriptionDelta for any post stored before the Delta migration
// (HTML in `description`, NULL descriptionDelta), now that the column exists.
// Idempotent and race-safe (guarded by descriptionDelta IS NULL); runs on the
// runtime connection, which has the UPDATE it needs. A no-op once all posts are
// converted - the same step Installer::attemptSilentUpgrade() runs on the web path.
$backfilled_before = mysqli_query($mysqli, 'SELECT COUNT(*) AS `n` FROM `Posts` WHERE `descriptionDelta` IS NULL AND `description` IS NOT NULL');
$pending = $backfilled_before ? (int) mysqli_fetch_assoc($backfilled_before)['n'] : 0;
PostDeltaBackfill::run($mysqli);
ok('post rich-text backfilled to Delta where needed (' . $pending . ' post(s) had legacy HTML)');

// Backfill forensic snapshots for any report created before snapshots existed,
// from whatever content is still around. Idempotent (snapshot IS NULL guard).
$reports_pending = mysqli_query($mysqli, 'SELECT COUNT(*) AS `n` FROM `Reports` WHERE `snapshot` IS NULL');
$reports_to_snapshot = $reports_pending ? (int) mysqli_fetch_assoc($reports_pending)['n'] : 0;
Report::backfillSnapshots();
ok('report snapshots backfilled where needed (' . $reports_to_snapshot . ' report(s) had none)');

// Extract hashtags from existing posts into the Hashtags/PostHashtags tables and
// the keywords column. Idempotent (attach uses INSERT IGNORE / upsert).
Hashtag::backfill();
ok('hashtags backfilled from existing posts');

// schema.sql also carries a handful of idempotent index migrations (ALTER
// TABLE ... ADD/DROP INDEX IF NOT EXISTS/IF EXISTS) - DDL, so unlike the
// UPDATE above these need admin privileges the runtime account deliberately
// doesn't have. Only reach for admin credentials when one is actually still
// needed (an already-applied migration is a no-op and shouldn't force a
// prompt on every healthy re-run - same principle as the schema drift step).
$needed_index_migrations = SchemaInstaller::neededIndexMigrations($mysqli);

if ($needed_index_migrations === []) {
    ok('index migrations up to date (nothing to apply)');
} else {
    $index_admin_mysqli = admin_connection($config, 'apply ' . count($needed_index_migrations) . ' pending index migration(s) from schema.sql');

    if ($index_admin_mysqli === null) {
        fail(
            count($needed_index_migrations) . ' index migration(s) from schema.sql are still pending: '
            . implode('; ', $needed_index_migrations) . '. '
            . 'Apply them as a MySQL admin, or set DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD and re-run.'
        );
    }

    foreach ($needed_index_migrations as $statement) {
        try {
            mysqli_query($index_admin_mysqli, $statement);
            ok('applied: ' . $statement);
        } catch (\mysqli_sql_exception $exception) {
            fail('Failed to apply index migration (' . $statement . '): ' . $exception -> getMessage());
        }
    }

    mysqli_close($index_admin_mysqli);
}

// Record the code version the database now matches - init.php locks the site
// to a maintenance page while the two disagree, so this is what unlocks it
// after an upgrade. The runtime connection can write it (plain INSERT/UPDATE).
$version_name = 'appVersion';
$code_version = Installer::codeVersion();
$version_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Settings` (`name`, `value`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `value` = VALUES(`value`)
');
mysqli_stmt_bind_param($version_stmt, 'ss', $version_name, $code_version);

try {
    mysqli_stmt_execute($version_stmt);
} catch (\mysqli_sql_exception $exception) {
    fail('Could not record the database version: ' . $exception -> getMessage());
}

ok('database marked as version ' . $code_version);

// ---------- Done ----------

echo "\n" . color('All checks passed.', '1;32') . "\n\n";
echo "Next steps:\n";
echo "  1. If .env was just created, restart the WebSocket server so it picks up the fresh\n";
echo "     WS_SECRET: systemctl --user restart glommer-websocket\n";
echo "  2. Visit " . $config['siteURL'] . " and sign up - the first account created becomes\n";
echo "     the site's administrator.\n";
