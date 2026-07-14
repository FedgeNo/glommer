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
 * The contents of the WebSocket service's systemd unit file - the single source
 * used both when first installing the service and when reconciling an existing
 * one to the current template (so the two can never drift apart).
 *
 * $run_as null produces a user-level unit (`systemctl --user`, WantedBy
 * default.target - the no-root default). A username produces a system-level unit
 * that runs as that user (WantedBy multi-user.target, started on boot with no
 * lingering) - what the root/sudo path installs.
 */
function websocket_unit_contents(?string $run_as = null): string
{
    $project_root = dirname(__DIR__);

    $service = ['[Service]'];

    if ($run_as !== null) {
        $service[] = 'User=' . $run_as;
    }

    // Quote the binary and script path - systemd splits ExecStart on whitespace,
    // so an install path or PHP binary path containing a space would otherwise
    // break the command into the wrong arguments.
    $service[] = 'ExecStart="' . PHP_BINARY . '" "' . $project_root . '/bin/websocket-server.php"';
    $service[] = 'Restart=always';
    $service[] = 'RestartSec=2';
    // Watchdog: the daemon pings WATCHDOG=1 every ~15s (half this); if its event
    // loop hangs and the pings stop, systemd kills and restarts it - catching a
    // wedged process that a plain crash-restart never would.
    $service[] = 'WatchdogSec=30';
    // Periodic restart: recycle the long-running process daily so any slow
    // resource growth can't accumulate indefinitely. Clients reconnect
    // automatically (main.js), so the brief blip is invisible.
    $service[] = 'RuntimeMaxSec=1d';
    $service[] = 'WorkingDirectory=' . $project_root;

    return implode("\n", array_merge(
        ['[Unit]', 'Description=Glommer WebSocket server', 'After=network.target', ''],
        $service,
        ['', '[Install]', 'WantedBy=' . ($run_as !== null ? 'multi-user.target' : 'default.target')]
    )) . "\n";
}

/**
 * Brings an already-installed WebSocket service's unit file up to the current
 * template on every install run, so changes to the unit (a new WatchdogSec/
 * RuntimeMaxSec, an updated ExecStart, ...) are picked up even when nothing looks
 * broken. Fully idempotent: a no-op when the on-disk contents already match; when
 * they differ it rewrites, daemon-reloads, and - because settings like
 * WatchdogSec only take effect at start - restarts the service if it's running.
 */
function reconcile_websocket_service_unit(): void
{
    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
        return;
    }

    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-websocket.service';

    // Only reconcile a unit that's already installed.
    if (!is_file($unit_path)) {
        return;
    }

    $desired = websocket_unit_contents();

    if ((string) @file_get_contents($unit_path) === $desired) {
        ok('WebSocket service unit already matches the current template');

        return;
    }

    if (file_put_contents($unit_path, $desired) === false) {
        warn('Could not update ' . $unit_path . ' - update it by hand, then: systemctl --user daemon-reload && systemctl --user restart glommer-websocket');

        return;
    }

    shell_exec('systemctl --user daemon-reload 2>&1');

    $was_active = trim((string) shell_exec('systemctl --user is-active glommer-websocket.service 2>/dev/null')) === 'active';

    if ($was_active) {
        shell_exec('systemctl --user restart glommer-websocket.service 2>&1');
    }

    ok('WebSocket service unit updated to the current template' . ($was_active ? ' and the service restarted' : ''));
}

/**
 * Writes the WebSocket service's unit file (websocket_unit_contents), enables it
 * to start now and on boot, and sets up the lingering it needs to survive logout
 * and reboot. Used when the service isn't installed/enabled yet: by
 * offer_websocket_service() (the daemon isn't even reachable) and
 * offer_enable_websocket_service() (the daemon works right now - e.g. started
 * manually - but isn't enabled/lingering isn't set, so it won't survive a
 * restart). Idempotent: re-writing identical contents and re-enabling an
 * already-enabled service are both no-ops.
 */
function write_and_enable_websocket_service(): bool
{
    $systemctl_available = trim((string) shell_exec('command -v systemctl 2>/dev/null')) !== '';

    if (!$systemctl_available) {
        warn('systemctl not found - run bin/websocket-server.php under your own process manager. See README.md.');

        return false;
    }

    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-websocket.service';
    $unit_contents = websocket_unit_contents();

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
 * The contents of the upload-worker service's systemd unit file - the single
 * source used both when first installing the service and when reconciling an
 * existing one to the current template (so the two can never drift apart).
 */
function upload_worker_unit_contents(?string $run_as = null): string
{
    $project_root = dirname(__DIR__);

    $service = ['[Service]'];

    if ($run_as !== null) {
        $service[] = 'User=' . $run_as;
    }

    // Quote the binary and script path - systemd splits ExecStart on whitespace
    // (see the WebSocket unit).
    $service[] = 'ExecStart="' . PHP_BINARY . '" "' . $project_root . '/bin/upload-worker.php"';
    $service[] = 'Restart=always';
    $service[] = 'RestartSec=2';
    // Watchdog: the supervisor pings WATCHDOG=1 every ~15s (half this); if its
    // loop hangs and the pings stop, systemd restarts it. Unlike the WS daemon
    // there's no RuntimeMaxSec periodic restart - the supervisor holds no
    // long-lived state, and a timed restart would needlessly interrupt an
    // in-flight transcode that the graceful stop then has to re-queue.
    $service[] = 'WatchdogSec=30';
    $service[] = 'WorkingDirectory=' . $project_root;

    return implode("\n", array_merge(
        ['[Unit]', 'Description=Glommer media upload worker', 'After=network.target', ''],
        $service,
        ['', '[Install]', 'WantedBy=' . ($run_as !== null ? 'multi-user.target' : 'default.target')]
    )) . "\n";
}

/**
 * Brings an already-installed upload-worker unit up to the current template on
 * every install run (a no-op when it already matches). Mirrors
 * reconcile_websocket_service_unit().
 */
function reconcile_upload_worker_service_unit(): void
{
    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
        return;
    }

    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-upload-worker.service';

    if (!is_file($unit_path)) {
        return;
    }

    $desired = upload_worker_unit_contents();

    if ((string) @file_get_contents($unit_path) === $desired) {
        ok('Upload worker service unit already matches the current template');

        return;
    }

    if (file_put_contents($unit_path, $desired) === false) {
        warn('Could not update ' . $unit_path . ' - update it by hand, then: systemctl --user daemon-reload && systemctl --user restart glommer-upload-worker');

        return;
    }

    shell_exec('systemctl --user daemon-reload 2>&1');

    $was_active = trim((string) shell_exec('systemctl --user is-active glommer-upload-worker.service 2>/dev/null')) === 'active';

    if ($was_active) {
        shell_exec('systemctl --user restart glommer-upload-worker.service 2>&1');
    }

    ok('Upload worker service unit updated to the current template' . ($was_active ? ' and the service restarted' : ''));
}

/**
 * Writes the upload-worker service's unit file, enables it to start now and on
 * boot, and sets up lingering. Mirrors write_and_enable_websocket_service().
 */
function write_and_enable_upload_worker_service(): bool
{
    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
        warn('systemctl not found - run bin/upload-worker.php under your own process manager. See README.md.');

        return false;
    }

    $unit_path = ($_SERVER['HOME'] ?? getenv('HOME')) . '/.config/systemd/user/glommer-upload-worker.service';
    $unit_contents = upload_worker_unit_contents();

    if (!is_dir(dirname($unit_path)) && !@mkdir(dirname($unit_path), 0755, true)) {
        fail_line('Could not create ' . dirname($unit_path) . ' - create the unit manually (see README.md).');

        return false;
    }

    if (file_put_contents($unit_path, $unit_contents) === false) {
        fail_line('Could not write ' . $unit_path . ' - create the unit manually (see README.md).');

        return false;
    }

    shell_exec('systemctl --user daemon-reload 2>&1');
    $enable_output = (string) shell_exec('systemctl --user enable --now glommer-upload-worker.service 2>&1');

    sleep(1);

    $status = trim((string) shell_exec('systemctl --user is-active glommer-upload-worker.service 2>/dev/null'));

    if ($status !== 'active') {
        fail_line('The service was written but did not start (' . trim($enable_output) . '). Check: systemctl --user status glommer-upload-worker');

        return false;
    }

    ok('Upload worker service installed and started (' . $unit_path . ')');

    $user = trim((string) shell_exec('id -un 2>/dev/null'));

    if ($user === '') {
        $user = get_current_user() ?: (string) getenv('USER');
    }

    shell_exec('loginctl enable-linger ' . escapeshellarg($user) . ' 2>&1');
    $linger_output = strtolower(trim((string) shell_exec('loginctl show-user ' . escapeshellarg($user) . ' --property=Linger 2>/dev/null')));

    if (str_contains($linger_output, 'yes')) {
        ok('Lingering enabled for ' . $user . ' - the worker keeps running after logout and starts on boot.');
    } else {
        warn('Could not enable lingering for ' . $user . ' automatically. The worker will stop when this user');
        echo '       logs out (and won\'t start on boot) until you run: sudo loginctl enable-linger ' . $user . "\n";
    }

    return true;
}

/**
 * When the upload-worker persistence check failed - the service isn't enabled
 * (staged uploads would queue forever) - offer to install and enable it.
 */
function offer_enable_upload_worker_service(): bool
{
    echo "\nThe media upload-worker service (glommer-upload-worker.service) isn't enabled -\n";
    echo "without it, staged video/audio uploads are never transcoded (they queue forever).\n";

    if (!confirm('Set it up now?')) {
        return false;
    }

    return write_and_enable_upload_worker_service();
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
/**
 * The systemd unit contents for the nightly backup - the single source used when
 * first installing the units and when reconciling existing ones to the current
 * template, so the two can't drift apart.
 */
function backup_service_contents(?string $run_as = null): string
{
    $service = ['[Service]', 'Type=oneshot'];

    if ($run_as !== null) {
        $service[] = 'User=' . $run_as;
    }

    $service[] = 'ExecStart=' . PHP_BINARY . ' ' . __DIR__ . '/backup.php';

    return implode("\n", array_merge(
        ['[Unit]', 'Description=Glommer backup', ''],
        $service
    )) . "\n";
}

function backup_timer_contents(): string
{
    return implode("\n", [
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
}

/**
 * Brings the already-installed backup service + timer units up to the current
 * template on every install run, so a changed schedule or ExecStart is picked up
 * even when nothing looks broken. Fully idempotent: a no-op when both files
 * already match; when either differs it rewrites the differing one and does a
 * daemon-reload (the timer fires the oneshot service on its schedule, so there's
 * nothing to restart).
 */
function reconcile_backup_timer_units(): void
{
    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
        return;
    }

    $home = ($_SERVER['HOME'] ?? getenv('HOME'));
    $service_path = $home . '/.config/systemd/user/glommer-backup.service';
    $timer_path = $home . '/.config/systemd/user/glommer-backup.timer';

    // Only reconcile units that are already installed.
    if (!is_file($service_path) || !is_file($timer_path)) {
        return;
    }

    $desired_service = backup_service_contents();
    $desired_timer = backup_timer_contents();
    $service_matches = (string) @file_get_contents($service_path) === $desired_service;
    $timer_matches = (string) @file_get_contents($timer_path) === $desired_timer;

    if ($service_matches && $timer_matches) {
        ok('Backup timer units already match the current template');

        return;
    }

    $written = true;

    if (!$service_matches) {
        $written = file_put_contents($service_path, $desired_service) !== false && $written;
    }

    if (!$timer_matches) {
        $written = file_put_contents($timer_path, $desired_timer) !== false && $written;
    }

    if (!$written) {
        warn('Could not update the backup units - update them by hand, then: systemctl --user daemon-reload');

        return;
    }

    shell_exec('systemctl --user daemon-reload 2>&1');
    ok('Backup timer units updated to the current template');
}

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

    $service_contents = backup_service_contents();
    $timer_contents = backup_timer_contents();

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

/**
 * Whether this run has root privileges (a `sudo php bin/install.php`). Only then
 * can the installer replace the user-level systemd services with SYSTEM units
 * (run as the app's unprivileged user, started on boot with no lingering) and
 * tighten uploads/ ownership. Uses `id -u` since ext-posix isn't guaranteed.
 */
function running_as_root(): bool
{
    return trim((string) shell_exec('id -u 2>/dev/null')) === '0';
}

/**
 * Maps a numeric uid to a username via getent, then /etc/passwd. Null if neither
 * is available or the uid isn't found (nothing here is assumed present).
 */
function uid_to_username(int $uid): ?string
{
    $line = trim((string) shell_exec('getent passwd ' . escapeshellarg((string) $uid) . ' 2>/dev/null'));

    if ($line !== '') {
        $name = explode(':', $line)[0];

        return $name !== '' ? $name : null;
    }

    foreach (@file('/etc/passwd', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $entry) {
        $fields = explode(':', $entry);

        if (isset($fields[2]) && (int) $fields[2] === $uid) {
            return $fields[0];
        }
    }

    return null;
}

/**
 * The unprivileged account the app's system services should run as under the
 * root path: whoever invoked sudo ($SUDO_USER), else the owner of the project
 * files. Never root. Null if it can't be resolved (we won't guess).
 */
function app_service_user(): ?string
{
    $sudo_user = getenv('SUDO_USER');

    if (is_string($sudo_user) && $sudo_user !== '' && $sudo_user !== 'root') {
        return $sudo_user;
    }

    $owner = @fileowner(dirname(__DIR__));

    if ($owner !== false && $owner !== 0) {
        $name = uid_to_username($owner);

        if ($name !== null && $name !== 'root') {
            return $name;
        }
    }

    return null;
}

/**
 * The account that owns the existing .env, or null when there's no .env (or it's
 * root-owned - a broken state we don't want to propagate). The web server has to
 * be able to read .env, so its owner IS the web-server user - which makes it the
 * stable choice for the service account when the live web-user probe comes back
 * empty, instead of flip-flopping the services to the sudo invoker.
 */
function env_file_owner(): ?string
{
    $env_path = dirname(__DIR__) . '/.env';

    if (!is_file($env_path)) {
        return null;
    }

    $owner = @fileowner($env_path);

    if ($owner === false) {
        return null;
    }

    $name = uid_to_username($owner);

    return $name !== null && $name !== 'root' ? $name : null;
}

/**
 * The web server's Unix account (user + primary group) from the live web-SAPI
 * facts, so uploads/ can be group-shared with it. Null when the web server
 * can't be reached or its uid can't be mapped - the caller then leaves uploads/
 * world-writable (0777), exactly as the unprivileged installer does.
 *
 * @return array{user: string, group: ?string}|null
 */
function web_server_account(): ?array
{
    $uid = EnvironmentChecker::webServerUid();

    if ($uid === null) {
        return null;
    }

    $user = uid_to_username($uid);

    if ($user === null) {
        return null;
    }

    $gid = trim((string) shell_exec('id -g ' . escapeshellarg($user) . ' 2>/dev/null'));
    $group = null;

    if ($gid !== '' && ctype_digit($gid)) {
        $group_line = trim((string) shell_exec('getent group ' . escapeshellarg($gid) . ' 2>/dev/null'));
        $group = $group_line !== '' ? (explode(':', $group_line)[0] ?: null) : null;
    }

    return ['user' => $user, 'group' => $group];
}

/**
 * Runs a `systemctl --user` command inside $user's own systemd instance from
 * this root context - needed to stop and disable the user-level services a prior
 * unprivileged install left running, before system units replace them. Requires
 * sudo and the user's runtime dir; returns '' if either is missing.
 */
function user_systemctl(string $user, string $args): string
{
    if (trim((string) shell_exec('command -v sudo 2>/dev/null')) === '') {
        return '';
    }

    $uid = trim((string) shell_exec('id -u ' . escapeshellarg($user) . ' 2>/dev/null'));

    if ($uid === '' || !ctype_digit($uid)) {
        return '';
    }

    return (string) shell_exec(
        'sudo -u ' . escapeshellarg($user)
        . ' XDG_RUNTIME_DIR=/run/user/' . $uid
        . ' systemctl --user ' . $args . ' 2>&1'
    );
}

/**
 * Stops, disables and removes a user-level unit a prior unprivileged install set
 * up for $user, so it can't keep running (holding a port or a flock) alongside
 * the system unit replacing it. `--now` on disable kills the live process, not
 * just the file. Best-effort.
 */
function remove_user_service(string $user, string $unit): void
{
    user_systemctl($user, 'disable --now ' . escapeshellarg($unit));
    user_systemctl($user, 'daemon-reload');

    $passwd = trim((string) shell_exec('getent passwd ' . escapeshellarg($user) . ' 2>/dev/null'));
    $home = $passwd !== '' ? (explode(':', $passwd)[5] ?? '') : '';

    if ($home !== '') {
        $path = $home . '/.config/systemd/user/' . $unit;

        if (is_file($path)) {
            @unlink($path);
        }
    }
}

/**
 * Migrates one long-running service (WebSocket, upload worker) to a system unit
 * running as $service_user. Stops the old user-level instance FIRST so its port/
 * flock is free, writes and (re)starts the system unit, and removes the old user
 * unit only once the system one is confirmed active. If the system unit won't
 * start and there was no prior system unit, the old user unit is restarted so
 * the site isn't left without the service. Returns whether it's active.
 */
function migrate_service_to_system(string $unit, string $contents, string $service_user, string $prior_user): bool
{
    $system_path = '/etc/systemd/system/' . $unit;
    $existing = is_file($system_path) ? (string) @file_get_contents($system_path) : null;
    $active = trim((string) shell_exec('systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null')) === 'active';

    // Already installed as a current system unit - just retire any lingering
    // user-level copy and move on.
    if ($existing === $contents && $active) {
        remove_user_service($prior_user, $unit);
        ok($unit . ' already installed as a system service (runs as ' . $service_user . ')');

        return true;
    }

    // Free the port/flock the OLD user-level daemon holds before (re)starting.
    // That daemon belongs to $prior_user (the admin who set it up), which is NOT
    // necessarily $service_user (the account the new system unit runs as) - so
    // stopping the wrong user's manager here is exactly what left the old daemon
    // holding the WS port / worker flock so the system unit couldn't bind.
    user_systemctl($prior_user, 'stop ' . escapeshellarg($unit));

    if (file_put_contents($system_path, $contents) === false) {
        fail_line('Could not write ' . $system_path . ' - ' . $unit . ' left as-is.');

        if ($existing !== null) {
            @file_put_contents($system_path, $existing);
        }

        user_systemctl($prior_user, 'start ' . escapeshellarg($unit));

        return false;
    }

    shell_exec('systemctl daemon-reload 2>&1');
    shell_exec('systemctl enable ' . escapeshellarg($unit) . ' 2>&1');
    shell_exec('systemctl restart ' . escapeshellarg($unit) . ' 2>&1');

    if (!system_unit_healthy($unit)) {
        fail_line('System unit ' . $unit . ' did not come up cleanly - check: systemctl status ' . $unit);

        // Restore whatever was working before rather than leave the site with a
        // dead service. A prior (good) system unit is written back and
        // restarted; otherwise fall back to the user-level service. The
        // user-level unit is only removed once the system unit is confirmed
        // healthy (below), so the fallback always still exists here.
        if ($existing !== null) {
            @file_put_contents($system_path, $existing);
            shell_exec('systemctl daemon-reload 2>&1');
            shell_exec('systemctl restart ' . escapeshellarg($unit) . ' 2>&1');
            warn('Reverted ' . $unit . ' to its previous system unit.');
        } else {
            @unlink($system_path);
            shell_exec('systemctl daemon-reload 2>&1');
            user_systemctl($prior_user, 'start ' . escapeshellarg($unit));
            warn('Reverted ' . $unit . ' to the user-level service.');
        }

        return false;
    }

    remove_user_service($prior_user, $unit);
    ok($unit . ' installed as a system service (runs as ' . $service_user . ')');

    return true;
}

/**
 * Whether a just-(re)started unit is genuinely up, not just momentarily active
 * between crash-loop restarts: it must be active AND have logged no automatic
 * restarts a few seconds in (Restart=always would otherwise mask a service that
 * starts then immediately dies - e.g. a system-context config it can't read).
 */
function system_unit_healthy(string $unit): bool
{
    sleep(3);

    $active = trim((string) shell_exec('systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null')) === 'active';
    $restarts = (int) trim((string) shell_exec('systemctl show -p NRestarts --value ' . escapeshellarg($unit) . ' 2>/dev/null'));

    return $active && $restarts === 0;
}

/**
 * Migrates the backup service+timer to system units running as $service_user
 * (the timer runs the oneshot service on schedule; only the timer is enabled),
 * and fires one backup now so the functional Backups check passes. Returns
 * whether the system timer ended up enabled.
 */
function migrate_backup_to_system(string $service_user, string $prior_user, bool $run_first_backup): bool
{
    if (file_put_contents('/etc/systemd/system/glommer-backup.service', backup_service_contents($service_user)) === false
        || file_put_contents('/etc/systemd/system/glommer-backup.timer', backup_timer_contents()) === false) {
        fail_line('Could not write the system backup units.');

        return false;
    }

    shell_exec('systemctl daemon-reload 2>&1');
    shell_exec('systemctl enable --now glommer-backup.timer 2>&1');

    if (trim((string) shell_exec('systemctl is-enabled glommer-backup.timer 2>/dev/null')) !== 'enabled') {
        fail_line('System backup timer did not enable - check: systemctl status glommer-backup.timer');

        return false;
    }

    remove_user_service($prior_user, 'glommer-backup.timer');
    remove_user_service($prior_user, 'glommer-backup.service');

    // The backup now runs as $service_user, so make its output directory
    // owned by that account (best-effort, top level only - enough to create and
    // prune backup subdirectories in it).
    $backup_dir = Env::get('BACKUP_DIR', '') ?: (dirname(dirname(__DIR__)) . '/glommer-backups');

    if (is_dir($backup_dir) && !is_link($backup_dir)) {
        @chown($backup_dir, $service_user);
    }

    // Fire one backup now (synchronous oneshot) ONLY if none has completed yet,
    // so the functional Backups check passes - without re-running a full backup
    // on every idempotent re-invocation of this installer.
    if ($run_first_backup) {
        shell_exec('systemctl start glommer-backup.service 2>&1');
    }

    ok('glommer-backup.timer installed as a system timer (runs backups as ' . $service_user . ')');

    return true;
}

/**
 * Recursively applies owner/group/mode across a tree using PHP's own chown/chgrp/
 * chmod (so it needs no external chown/find/chmod binary). Directories get
 * $dir_mode, files $file_mode. Best-effort per entry.
 */
function chown_tree(string $root, string $owner, string $group, int $dir_mode, int $file_mode): void
{
    if (!is_dir($root) || is_link($root)) {
        return;
    }

    $apply = static function (string $path, bool $is_dir) use ($owner, $group, $dir_mode, $file_mode): void {
        @chown($path, $owner);
        @chgrp($path, $group);
        @chmod($path, $is_dir ? $dir_mode : $file_mode);
    };

    $apply($root, true);

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        // NEVER follow a symlink: chown/chgrp/chmod dereference them, so a
        // symlink planted in the web-writable uploads/ tree (uploads/x ->
        // /root/.ssh) would otherwise let us chown/chmod an attacker-chosen path
        // as root - a privilege-escalation primitive. Skip links entirely.
        if ($item -> isLink()) {
            continue;
        }

        $apply($item -> getPathname(), $item -> isDir());
    }
}

/**
 * Makes every directory under $root world-writable (0777) - the fallback when
 * the web server's account can't be detected, so it can still create files
 * there (what the unprivileged installer relies on). Files are left alone. Pure
 * PHP, no external tool.
 */
function make_dirs_world_writable(string $root): void
{
    if (!is_dir($root) || is_link($root)) {
        return;
    }

    @chmod($root, 0777);

    // SELF_FIRST so real directories are actually yielded (the default
    // LEAVES_ONLY mode yields only files/leaves); skip symlinks (see chown_tree).
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        if (!$item -> isLink() && $item -> isDir()) {
            @chmod($item -> getPathname(), 0777);
        }
    }
}

/**
 * Sets uploads/ ownership and permissions for the root path. With the web
 * server's account known, uploads/ is owned by the service user, group-shared
 * with the web server's group (setgid + group-write on directories) so both can
 * manage files without world-writable, and .env is locked to 0640. Without it,
 * uploads/ stays world-writable and a warning explains why.
 */
function fix_upload_ownership(string $service_user, ?array $web): void
{
    $uploads = dirname(__DIR__) . '/uploads';
    $env_file = dirname(__DIR__) . '/.env';

    if ($web === null) {
        warn('Could not detect the web server\'s user (its live facts were unreachable - e.g. a non-default VirtualHost on 127.0.0.1). Leaving uploads/ world-writable (0777), as the unprivileged installer does, so the web server can still write there; re-run with the web server reachable to tighten this.');
        make_dirs_world_writable($uploads);

        return;
    }

    // The daemons now run AS the web-server user (see set_up_system_services), so
    // a single account owns and writes everything under uploads/ - the web
    // requests that stage uploads AND the worker that transcodes them are the
    // same user. So it can simply be owned by that user with ordinary perms: no
    // world-writable, and none of the cross-account group juggling that doesn't
    // actually work when the web and worker are different users.
    chown_tree($uploads, $service_user, $web['group'] ?? $service_user, 0755, 0644);

    if (is_file($env_file)) {
        @chown($env_file, $service_user);
        @chgrp($env_file, $web['group'] ?? $service_user);
        @chmod($env_file, 0640);
    }

    ok('uploads/ owned by ' . $service_user . ' (the web-server user) with ordinary perms - no longer world-writable; the daemons run as the same account.');
}

/**
 * Whether $user can actually read $path (traversal included, not just the file's
 * own mode). Tested by trying to read it AS that user via sudo. Assumes yes when
 * it can't test (no sudo), so it never needlessly relocates a fine cert.
 */
function web_user_can_read(string $user, string $path): bool
{
    if (trim((string) shell_exec('command -v sudo 2>/dev/null')) === '') {
        return true;
    }

    exec('sudo -u ' . escapeshellarg($user) . ' test -r ' . escapeshellarg($path) . ' 2>/dev/null', $output, $exit_code);

    return $exit_code === 0;
}

/**
 * Makes sure the WebSocket daemon - which now runs as the web-server user - can
 * read its TLS cert/key for wss://. Chowning the files isn't enough when they
 * sit in a 0700 home dir (a mkcert cert's usual home): the web user can't even
 * traverse to them. So when the web user can't read them, the cert+key are
 * copied to a shared, readable location (/etc/glommer) and .env is repointed
 * there. A cert already readable by the web user (e.g. a Let's Encrypt cert
 * under /etc) is left untouched. Runs before the WS service is (re)started, so
 * it comes up with the reachable path.
 */
function ensure_ws_cert_readable(string $service_user, ?array $web): void
{
    $cert = Env::get('WS_TLS_CERT');
    $key = Env::get('WS_TLS_KEY');

    if (!is_string($cert) || $cert === '' || !is_string($key) || $key === '' || !is_file($cert) || !is_file($key)) {
        return;
    }

    if (web_user_can_read($service_user, $cert) && web_user_can_read($service_user, $key)) {
        return;
    }

    $group = $web !== null ? ($web['group'] ?? $service_user) : $service_user;
    $dest_dir = '/etc/glommer';

    if (!is_dir($dest_dir) && !@mkdir($dest_dir, 0755, true)) {
        warn('The WebSocket cert (' . $cert . ') isn\'t readable by ' . $service_user . ' and ' . $dest_dir . ' couldn\'t be created - wss:// will fail. Move the cert somewhere ' . $service_user . ' can read and set WS_TLS_CERT/WS_TLS_KEY to it.');

        return;
    }

    @chmod($dest_dir, 0755);

    $dest_cert = $dest_dir . '/ws-cert.pem';
    $dest_key = $dest_dir . '/ws-key.pem';

    if (!@copy($cert, $dest_cert) || !@copy($key, $dest_key)) {
        warn('The WebSocket cert isn\'t readable by ' . $service_user . ' and couldn\'t be copied to ' . $dest_dir . ' - wss:// will fail. Move it there manually and repoint WS_TLS_CERT/WS_TLS_KEY.');

        return;
    }

    // Cert is public (0644); key is group-readable to the web-server group only.
    @chgrp($dest_cert, $group);
    @chmod($dest_cert, 0644);
    @chgrp($dest_key, $group);
    @chmod($dest_key, 0640);

    $env_path = dirname(__DIR__) . '/.env';
    $env_contents = (string) @file_get_contents($env_path);
    $env_contents = preg_replace('/^WS_TLS_CERT=.*$/m', 'WS_TLS_CERT="' . $dest_cert . '"', $env_contents, -1, $cert_replaced);
    $env_contents = preg_replace('/^WS_TLS_KEY=.*$/m', 'WS_TLS_KEY="' . $dest_key . '"', $env_contents, -1, $key_replaced);

    if ($cert_replaced && $key_replaced && @file_put_contents($env_path, $env_contents) !== false) {
        putenv('WS_TLS_CERT=' . $dest_cert);
        putenv('WS_TLS_KEY=' . $dest_key);

        // The cert path lives in .env, not the unit file, so an already-installed
        // WS system service (its unit unchanged) wouldn't restart on its own to
        // pick up the new path. Restart it here if it's running, so it reloads
        // .env now; a not-yet-migrated (user) service is handled by the migration
        // that follows.
        if (trim((string) shell_exec('systemctl is-active glommer-websocket.service 2>/dev/null')) === 'active') {
            shell_exec('systemctl restart glommer-websocket.service 2>&1');
        }

        ok('WebSocket cert relocated to ' . $dest_dir . ' (it was unreadable by ' . $service_user . ' in a home dir) and .env repointed.');
    } else {
        warn('Copied the WebSocket cert to ' . $dest_dir . ' but couldn\'t update .env - set WS_TLS_CERT="' . $dest_cert . '" and WS_TLS_KEY="' . $dest_key . '" manually.');
    }
}

/**
 * The root/sudo path: replace the user-level services with system units, retire
 * the old user-level units, and fix uploads/ ownership. The services run AS the
 * web-server user when it's detectable (so one account owns and writes uploads/
 * with no world-writable), falling back to the sudo invoker (uploads/ left
 * 0777) when it isn't. Clears the environment failures it resolves. A
 * no-op-with-warning when its prerequisites aren't met - the user-level setup
 * then stays intact.
 */
function set_up_system_services(array &$environment_failures): void
{
    heading('System services (running as root)');

    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
        warn('systemctl not found - cannot install system services. Set up this box\'s process manager by hand (see README.md).');

        return;
    }

    $web = web_server_account();

    // Run the services AS the web-server user when we can detect it - then the
    // web server and the daemons are one account that owns uploads/ outright.
    // When the live probe can't detect it (e.g. the HTTPS-enforcement redirect
    // makes the loopback http check unreachable), fall back to whoever already
    // owns .env rather than the sudo invoker: the web server has to be able to
    // read .env, so its owner IS the web-server user, and keying off it keeps
    // every service on one stable account instead of flip-flopping the daemons
    // (and re-chowning .env/uploads) to a different user than the app actually
    // runs as on each run. Only a brand-new install with no .env falls through
    // to the invoker.
    $service_user = $web !== null ? $web['user'] : (env_file_owner() ?? app_service_user());

    if ($service_user === null) {
        warn('Could not determine a user to run the services as - the web-server user is undetectable and SUDO_USER is unset (invoke via `sudo`). Skipping system-service setup.');

        return;
    }

    // Whose EXISTING user-level services to retire: the admin who set them up
    // (the sudo invoker / project owner), which is NOT necessarily the account
    // the new system services run as ($service_user, the web-server user).
    $prior_user = app_service_user() ?? $service_user;

    // Fix ownership FIRST, before starting the system services: they run as the
    // web-server user, so .env and uploads/ (including the worker's lock file)
    // must already belong to that account the moment they come up - otherwise
    // they fail on a permission-denied at startup.
    fix_upload_ownership($service_user, $web);

    // And make sure the WS daemon (now the web-server user) can actually reach
    // its TLS cert - relocating it out of an unreadable home dir if need be -
    // before it's (re)started below.
    ensure_ws_cert_readable($service_user, $web);

    if (migrate_service_to_system('glommer-websocket.service', websocket_unit_contents($service_user), $service_user, $prior_user)) {
        // The service being active is enough to clear PERSISTENCE (it's an
        // enabled system unit now)...
        unset($environment_failures['WebSocket service persistence']);

        // ...but reachability means the daemon actually serves TLS, which a
        // bound-but-can't-read-its-cert daemon doesn't. Re-verify the real
        // handshake now that it runs as the web user with the (possibly
        // relocated) cert, and only clear the failure if it genuinely connects -
        // so a still-unreadable cert can't slip through as "all checks passed".
        sleep(1);
        $ws_recheck = EnvironmentChecker::checks()['WebSocket server'];

        if ($ws_recheck['ok']) {
            unset($environment_failures['WebSocket server']);
        } else {
            $environment_failures['WebSocket server'] = $ws_recheck['message'];
        }
    }

    if (migrate_service_to_system('glommer-upload-worker.service', upload_worker_unit_contents($service_user), $service_user, $prior_user)) {
        unset($environment_failures['Upload worker service persistence']);
    }

    if (migrate_backup_to_system($service_user, $prior_user, isset($environment_failures['Backups']) && is_file(__DIR__ . '/../.env'))) {
        unset($environment_failures['Backup timer persistence']);

        if (EnvironmentChecker::checks()['Backups']['ok']) {
            unset($environment_failures['Backups']);
        }
    }
}

/**
 * Whether $host is a real public domain a public CA (Let's Encrypt) could issue
 * for - i.e. not localhost, an IP, or a private/reserved/single-label name.
 * Automatic certbot only makes sense for these; localhost/dev needs mkcert.
 */
function host_is_public_domain(string $host): bool
{
    $host = strtolower($host);

    if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false) {
        return false;
    }

    if (!str_contains($host, '.')) {
        return false;
    }

    foreach (['.local', '.localhost', '.internal', '.test', '.example', '.invalid', '.home', '.lan'] as $suffix) {
        if (str_ends_with($host, $suffix)) {
            return false;
        }
    }

    return true;
}

/**
 * The binary name of this box's package manager ('dnf'|'apt-get'|'yum'|
 * 'zypper'|'pacman'), or null if none is found - nothing is assumed to be
 * present. dnf is preferred over yum where both exist. Shared by
 * package_install_command() and the prerequisite installer so neither
 * reinvents detection.
 */
function detected_package_manager(): ?string
{
    foreach (['dnf', 'apt-get', 'yum', 'zypper', 'pacman'] as $binary) {
        if (trim((string) shell_exec('command -v ' . escapeshellarg($binary) . ' 2>/dev/null')) !== '') {
            return $binary;
        }
    }

    return null;
}

/**
 * A best-effort package-install command prefix for this box's package manager
 * (dnf/apt/yum/zypper/pacman), or null if none is found - nothing is assumed to
 * be present. Non-interactive.
 */
function package_install_command(): ?string
{
    $commands = [
        'dnf' => 'dnf install -y',
        'apt-get' => 'apt-get install -y',
        'yum' => 'yum install -y',
        'zypper' => 'zypper --non-interactive install',
        'pacman' => 'pacman -S --noconfirm',
    ];

    $manager = detected_package_manager();

    return $manager === null ? null : $commands[$manager];
}

/**
 * Before anything else runs, make sure the box actually HAS what the installer
 * needs to reach provisioning: the required PHP CLI extensions, a MariaDB
 * server, and the mysqldump client. Auto-installs the missing pieces when run
 * as root on a distribution whose package names we know (RHEL/Debian families);
 * otherwise prints the exact packages to install by hand and exits.
 *
 * Called as the very first step of top-level execution - before any check,
 * before mysqli is touched - because a missing mysqli extension or absent DB
 * server would otherwise surface as a fatal deep inside provisioning.
 */
function ensure_system_prerequisites(): void
{
    // ---- 1. Detect what's missing ----

    // Mirrors EnvironmentChecker::checkExtensions' required list.
    $required_extensions = ['mysqli', 'gd', 'curl', 'dom', 'libxml', 'fileinfo', 'mbstring'];
    $missing_extensions = [];

    foreach ($required_extensions as $extension) {
        if (!extension_loaded($extension)) {
            $missing_extensions[] = $extension;
        }
    }

    // No DB server at all: neither a server binary on PATH nor a systemd unit
    // for one (the "Unit mariadb.service could not be found" case).
    $has_db_binary = trim((string) shell_exec('command -v mariadbd 2>/dev/null')) !== ''
        || trim((string) shell_exec('command -v mysqld 2>/dev/null')) !== '';
    $has_db_unit = false;

    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) !== '') {
        foreach (['mariadb.service', 'mysqld.service', 'mysql.service'] as $unit) {
            if (str_contains((string) shell_exec('systemctl list-unit-files ' . escapeshellarg($unit) . ' 2>/dev/null'), $unit)) {
                $has_db_unit = true;

                break;
            }
        }
    }

    $need_mariadb_server = !$has_db_binary && !$has_db_unit;

    // mysqldump specifically (bin/backup.php calls it by that name); the
    // client package also provides mariadb-dump, so either satisfies "present".
    $need_mysqldump = trim((string) shell_exec('command -v mysqldump 2>/dev/null')) === ''
        && trim((string) shell_exec('command -v mariadb-dump 2>/dev/null')) === '';

    if ($missing_extensions === [] && !$need_mariadb_server && !$need_mysqldump) {
        return;
    }

    // ---- 2. Human-readable list + package mapping ----

    $missing_labels = [];

    foreach ($missing_extensions as $extension) {
        $missing_labels[] = 'PHP extension: ' . $extension;
    }

    if ($need_mariadb_server) {
        $missing_labels[] = 'MariaDB server (no database server installed)';
    }

    if ($need_mysqldump) {
        $missing_labels[] = 'mysqldump (database backup client)';
    }

    $manager = detected_package_manager();

    $family_maps = [
        'rhel' => [
            'mysqli' => 'php-mysqlnd',
            'gd' => 'php-gd',
            'curl' => 'php-curl',
            'dom' => 'php-xml',
            'libxml' => 'php-xml',
            'fileinfo' => 'php-common',
            'mbstring' => 'php-mbstring',
            'mariadb-server' => 'mariadb-server',
            'mysqldump' => 'mariadb',
        ],
        'debian' => [
            'mysqli' => 'php-mysql',
            'gd' => 'php-gd',
            'curl' => 'php-curl',
            'dom' => 'php-xml',
            'libxml' => 'php-xml',
            'fileinfo' => 'php-common',
            'mbstring' => 'php-mbstring',
            'mariadb-server' => 'mariadb-server',
            'mysqldump' => 'mariadb-client',
        ],
    ];

    if ($manager === 'dnf' || $manager === 'yum') {
        $map = $family_maps['rhel'];
    } elseif ($manager === 'apt-get') {
        $map = $family_maps['debian'];
    } else {
        // zypper/pacman/unknown: we don't map names confidently - fall through
        // to the instructions-and-exit path.
        $map = null;
    }

    $packages = [];

    if ($map !== null) {
        foreach ($missing_extensions as $extension) {
            $packages[$map[$extension]] = true;
        }

        if ($need_mariadb_server) {
            $packages[$map['mariadb-server']] = true;
        }

        if ($need_mysqldump) {
            $packages[$map['mysqldump']] = true;
        }
    }

    $packages = array_keys($packages);
    $install_command = package_install_command();

    // ---- 3. Can't (or shouldn't) auto-install: tell the user what to run ----

    if (!running_as_root() || $install_command === null || $map === null) {
        heading('System prerequisites');
        echo "The installer needs these before it can continue, and they're missing:\n";

        foreach ($missing_labels as $label) {
            echo "  - " . $label . "\n";
        }

        echo "\n";

        if ($map !== null && $install_command !== null) {
            echo "Install them (as root), then re-run this script:\n";
            echo "  sudo " . $install_command . ' ' . implode(' ', array_map('escapeshellarg', $packages)) . "\n";
        } else {
            echo "Install the equivalents for your distribution - a MariaDB/MySQL server, the mysqldump\n";
            echo "client, and the missing php-* extensions - then re-run this script.\n";
        }

        exit(1);
    }

    // ---- 4. Auto-install (root + known package names) ----

    heading('System prerequisites');
    echo "Installing missing prerequisite package(s): " . implode(', ', $packages) . "\n\n";

    passthru($install_command . ' ' . implode(' ', array_map('escapeshellarg', $packages)), $install_code);

    if ($install_code !== 0) {
        fail('Package installation failed (exit code ' . $install_code . '). Install the package(s) above by hand, then re-run.');
    }

    ok('Installed: ' . implode(', ', $packages));

    // A freshly installed DB server isn't running yet - start and enable it so
    // the provisioning step right after this can actually connect. (This
    // enable/start goes slightly beyond a literal "install", but the DB is
    // unusable without it.)
    if ($need_mariadb_server) {
        $started = false;

        foreach (['mariadb.service', 'mysqld.service', 'mysql.service'] as $unit) {
            shell_exec('systemctl enable --now ' . escapeshellarg($unit) . ' 2>&1');

            if (trim((string) shell_exec('systemctl is-active ' . escapeshellarg($unit) . ' 2>/dev/null')) === 'active') {
                ok('Database server started and enabled on boot (' . $unit . ')');
                $started = true;

                break;
            }
        }

        if (!$started) {
            warn('Installed the database server but could not start it automatically - start it by hand (e.g. sudo systemctl enable --now mariadb), then re-run.');
        }
    }

    // ---- 5. A newly installed PHP extension isn't loaded in THIS process ----

    if ($missing_extensions !== []) {
        $still_missing = [];

        foreach ($missing_extensions as $extension) {
            if (!extension_loaded($extension)) {
                $still_missing[] = $extension;
            }
        }

        // We've already re-exec'd once and an extension STILL won't load:
        // the package is installed but its module isn't enabled for this PHP
        // (disabled/absent .ini, or built for a different PHP). Don't loop -
        // explain the manual fix and stop.
        if (getenv('GLOMMER_PREREQS_INSTALLED') === '1') {
            if ($still_missing !== []) {
                fail('Installed the PHP extension package(s), but ' . implode(', ', $still_missing) . ' still won\'t load - the module is likely disabled (a missing or commented-out .ini in ' . (PHP_CONFIG_FILE_SCAN_DIR ?: 'PHP\'s conf.d') . ') or built for a different PHP than this CLI. Enable it (add "extension=<name>" to a .ini there), then re-run.');
            }

            return;
        }

        // First install: re-exec a fresh PHP so the new extension(s) load, with
        // an env-var guard so a persistently-unloadable extension can't loop.
        putenv('GLOMMER_PREREQS_INSTALLED=1');

        $arg_string = '';

        foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
            $arg_string .= ' ' . escapeshellarg($arg);
        }

        ok('Re-launching the installer so the newly installed PHP extension(s) load...');
        passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . $arg_string, $reexec_code);
        exit($reexec_code);
    }
}

/**
 * The web server actually running, so we use the right certbot plugin - NOT an
 * assumption that it's Apache. nginx is checked first because in the reverse-
 * proxy deployments this app supports, nginx out front is what terminates TLS
 * (so the cert belongs there), with Apache behind it on loopback. Returns
 * 'nginx', 'apache', or null (unknown - don't guess, leave it manual).
 */
function running_web_server(): ?string
{
    if (trim((string) shell_exec('command -v pgrep 2>/dev/null')) === '') {
        return null;
    }

    if (trim((string) shell_exec('pgrep -x nginx 2>/dev/null')) !== '') {
        return 'nginx';
    }

    if (trim((string) shell_exec('pgrep -x httpd 2>/dev/null')) !== '' || trim((string) shell_exec('pgrep -x apache2 2>/dev/null')) !== '') {
        return 'apache';
    }

    return null;
}

/**
 * Installs certbot and the given plugin package via the box's package manager if
 * certbot isn't already present. Returns whether certbot is now available.
 */
function install_certbot(string $plugin_package): bool
{
    if (trim((string) shell_exec('command -v certbot 2>/dev/null')) !== '') {
        return true;
    }

    $install = package_install_command();

    if ($install === null) {
        return false;
    }

    echo "\nInstalling certbot (" . $install . ' certbot ' . $plugin_package . ")...\n";
    shell_exec($install . ' certbot ' . escapeshellarg($plugin_package) . ' 2>&1');

    return trim((string) shell_exec('command -v certbot 2>/dev/null')) !== '';
}

/**
 * Whether a plain HTTP request to http://$host comes back - the readiness signal
 * for a Let's Encrypt HTTP-01 challenge (DNS points here, port 80 open, Apache
 * serving this host). Any HTTP response counts; a connect failure doesn't.
 */
function http_reachable(string $host): bool
{
    if (!function_exists('curl_init')) {
        return false;
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://' . $host . '/',
        CURLOPT_NOBODY => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    curl_exec($curl);
    $errno = curl_errno($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    return $errno === 0 && $status > 0;
}

/**
 * Under a root install, tries to obtain the web-server TLS certificate
 * automatically: installs certbot (with the plugin for whichever web server is
 * actually running - Apache or nginx, never assumed) and runs it for the site's
 * hostname, so HTTPS gets set up without the manual step. Only for a real public
 * domain (Let's Encrypt can't issue for localhost) and only once the host is
 * reachable over HTTP (so a not-ready domain doesn't burn Let's Encrypt rate
 * limits). --no-redirect: the app does its own canonical, host-spoof-safe
 * http->https redirect (.htaccess + UseCanonicalName), which certbot's
 * Host-header-based redirect would undermine. --keep-until-expiring makes a
 * re-run reuse an existing cert. When the web server can't be identified we
 * install certbot but leave running it to the admin (we won't guess the plugin).
 * Returns whether a certificate was obtained.
 */
function attempt_web_certificate(string $host, string $email): bool
{
    if (!host_is_public_domain($host)) {
        warn('Automatic certificates need a real public domain - Let\'s Encrypt can\'t issue for ' . $host . '. For localhost/dev use mkcert (see README\'s HTTPS section).');

        return false;
    }

    $server = running_web_server();

    if ($server === null) {
        warn('Couldn\'t identify the web server (not nginx or Apache, or it isn\'t running yet) - not guessing a certbot plugin. If TLS terminates here, install certbot with the plugin for your server and run it manually; if a reverse proxy terminates TLS, the certificate belongs on the proxy (see README).');

        return false;
    }

    $plugin_package = $server === 'nginx' ? 'python3-certbot-nginx' : 'python3-certbot-apache';

    if (!install_certbot($plugin_package)) {
        warn('certbot isn\'t installed and couldn\'t be installed automatically (no known package manager found). Install it by hand - e.g. dnf install certbot ' . $plugin_package . ' - and re-run, or get a certificate manually (see README).');

        return false;
    }

    if (!http_reachable($host)) {
        warn('Not requesting a certificate yet: http://' . $host . ' isn\'t reachable, so a Let\'s Encrypt challenge would fail (DNS not pointing here, port 80 firewalled, or the web server not serving this host). Point DNS + open port 80, then re-run.');

        return false;
    }

    echo "\nObtaining a Let's Encrypt certificate for " . $host . ' (certbot --' . $server . ")...\n";

    $email_args = ($email !== '' && $email !== 'noreply@example.com' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
        ? '-m ' . escapeshellarg($email)
        : '--register-unsafely-without-email';

    exec('certbot --' . $server . ' --non-interactive --agree-tos --keep-until-expiring --no-redirect '
        . $email_args . ' -d ' . escapeshellarg($host) . ' 2>&1', $output, $exit_code);

    if ($exit_code !== 0) {
        warn('certbot did not complete (' . trim(implode(' | ', array_slice($output, -4))) . '). Fix the cause and re-run, or get a certificate manually (see README).');

        return false;
    }

    ok('Certificate obtained and ' . $server . ' configured for HTTPS on ' . $host);

    return true;
}

// ---------- 0. System prerequisites ----------

// Auto-install (or, when we can't, spell out) anything the installer needs
// before it reaches provisioning: the required PHP extensions, a DB server,
// and the mysqldump client. Runs first so a missing mysqli/DB server can't
// surface as a fatal deep inside the steps below.
ensure_system_prerequisites();

// ---------- 1. Environment ----------

heading('Environment');

// Whether the site is already configured (.env present). On a fresh box it
// isn't, so the checks that depend on a provisioned database or a configured
// site (backups, the WebSocket/upload-worker daemons) can't yet pass - those
// are deferred rather than blocking, so provisioning below can actually run.
$is_configured = is_file(__DIR__ . '/../.env');

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

// Root/sudo: upgrade any user-level services to system units (run as the app's
// unprivileged user, started on boot with no lingering), retire the old user
// units, and tighten uploads/ ownership. This clears the persistence failures
// it resolves, so the user-level offers below - which would otherwise install
// services for root - are skipped.
$is_root = running_as_root();

if ($is_root) {
    set_up_system_services($environment_failures);
}

// Same explicit, named non-interactive opt-in pattern as
// SERVERNAME_CONFIRMED/BACKUP_TIMER_CONFIRMED - these two WebSocket offers
// are purely mechanical (write a unit file, enable it, set lingering; both
// idempotent) with nothing to blindly trust, unlike ServerName, so a named
// opt-in unlocking them without a TTY is safe the same way it is for backups.
$websocket_non_interactive_ok = Env::get('WEBSOCKET_SERVICE_CONFIRMED', '') === '1';

// A missing WebSocket server is one environment failure this script can fix
// itself - offer to, then re-run just that check.
if (isset($environment_failures['WebSocket server']) && (is_interactive() || $websocket_non_interactive_ok) && !$is_root && offer_websocket_service()) {
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
if (isset($environment_failures['WebSocket service persistence']) && (is_interactive() || $websocket_non_interactive_ok) && !$is_root && offer_enable_websocket_service()) {
    $recheck = EnvironmentChecker::checks()['WebSocket service persistence'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['WebSocket service persistence']);
    } else {
        fail_line($recheck['message']);
    }
}

// Bring an already-installed unit up to the current template on every run (a
// no-op when it already matches), so a unit change is applied even when nothing
// looks broken.
reconcile_websocket_service_unit();

// The media upload-worker service (drains the async transcode queue). Offer to
// enable it if it isn't, then reconcile an already-installed unit to the current
// template - both mirroring the WebSocket service above.
if (isset($environment_failures['Upload worker service persistence']) && (is_interactive() || $websocket_non_interactive_ok) && !$is_root && offer_enable_upload_worker_service()) {
    $recheck = EnvironmentChecker::checks()['Upload worker service persistence'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['Upload worker service persistence']);
    } else {
        fail_line($recheck['message']);
    }
}

reconcile_upload_worker_service_unit();

// Likewise the one environment failure this script can fix itself by running
// a real backup on the spot. BACKUP_TIMER_CONFIRMED=1 is the same explicit,
// named non-interactive opt-in as SERVERNAME_CONFIRMED below - it unlocks
// offer_first_backup() without a TTY, but its confirm() calls still read real
// answers off stdin (a pipe works fine for that; only stream_isatty() cares
// whether it's a real terminal), so this isn't a blind bypass - an actual "y"
// still has to arrive for each step.
$backups_non_interactive_ok = Env::get('BACKUP_TIMER_CONFIRMED', '') === '1';

if (isset($environment_failures['Backups']) && is_file(__DIR__ . '/../.env') && (is_interactive() || $backups_non_interactive_ok) && !$is_root && offer_first_backup()) {
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
if (isset($environment_failures['Backup timer persistence']) && (is_interactive() || $backups_non_interactive_ok) && !$is_root && offer_enable_backup_timer()) {
    $recheck = EnvironmentChecker::checks()['Backup timer persistence'];

    if ($recheck['ok']) {
        ok($recheck['message']);
        unset($environment_failures['Backup timer persistence']);
    } else {
        fail_line($recheck['message']);
    }
}

// Bring already-installed backup units up to the current template on every run
// (a no-op when they already match).
reconcile_backup_timer_units();

// Checks that can't pass until the site is configured (DB provisioned / .env
// written): a backup needs a database to dump, and the daemons/timers are set
// up as part of a completed install. On a fresh box these are deferred past the
// gate so provisioning can run; everything else still blocks. Once configured,
// they block like any other failure.
$deferred_check_names = ['Backups', 'WebSocket server', 'WebSocket service persistence', 'Upload worker service persistence', 'Backup timer persistence'];

$deferred_failures = [];

if (!$is_configured) {
    foreach ($deferred_check_names as $name) {
        if (isset($environment_failures[$name])) {
            $deferred_failures[$name] = $environment_failures[$name];
            unset($environment_failures[$name]);
        }
    }

    foreach ($deferred_failures as $name => $message) {
        warn($name . ': deferred until configuration; will be re-checked after install completes.');
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

    // On a fresh MariaDB/MySQL the root account usually authenticates through the
    // local unix socket with no password rather than over TCP - so when this runs
    // under sudo (the OS-root process maps to the DB root), connect that way
    // automatically instead of failing on a password. The runtime account is
    // created for host '%' regardless (see Installer::provisionDatabase), so
    // provisioning over the socket rather than $db_host makes no difference.
    if ($is_root) {
        try {
            $admin_connection = mysqli_connect('localhost', 'root', '');
            ok('Connected to the database as root through the local socket (no admin password needed).');
        } catch (\mysqli_sql_exception $exception) {
            // root has a password set, or a non-standard socket - fall back to asking.
        }
    }

    if ($admin_connection === null) {
        echo "\nOne-time database admin credentials are needed to create the '" . $db_database . "' database\n";
        echo "and its runtime account (used once, never stored).\n";
        echo "On a fresh MariaDB/MySQL, root has no password and authenticates only over the local\n";
        echo "socket: re-run this under sudo to use it automatically, or create a password admin\n";
        echo "account first -\n\n";
        echo "  sudo mariadb -e \"CREATE USER 'glommer_admin'@'" . $db_host . "' IDENTIFIED BY 'a-strong-password'; GRANT ALL PRIVILEGES ON *.* TO 'glommer_admin'@'" . $db_host . "' WITH GRANT OPTION;\"\n\n";
        echo "- then enter that account below (or root and its password, if you have set one).\n\n";
    }

    while ($admin_connection === null) {
        $admin_username = prompt('Database admin username', 'root');
        $admin_password = prompt_hidden('Database admin password');

        try {
            $admin_connection = mysqli_connect($db_host, $admin_username, $admin_password, null, (int) $db_port);
        } catch (\mysqli_sql_exception $exception) {
            fail_line('Could not connect: ' . $exception -> getMessage());
            echo "       On a fresh MariaDB/MySQL, root only authenticates over the local socket - re-run\n";
            echo "       under sudo, or use a password admin account (see above). Try again.\n";
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

    // Under a root/sudo install .env was just created owned by root, but the web
    // server and daemons run as the (unprivileged) web-server user - hand the
    // file to that account so they can read it, keeping it 0600 (owner-only, so
    // the DB password / WS secret stay unreadable to anyone else).
    if ($is_root) {
        $env_web = web_server_account();
        $env_owner = $env_web !== null ? $env_web['user'] : app_service_user();

        if ($env_owner !== null) {
            @chown($env_path, $env_owner);
        }
    }

    ok('.env written');

    // The WebSocket daemon was (almost certainly) started before .env existed,
    // so it's running on config.php's default WS_SECRET - which makes it reject
    // every client whose token is signed with the real secret just written here,
    // and fails the WebSocket environment check until it's restarted. Restart it
    // now if we have the privilege to (a root install manages the system unit;
    // a non-root install manages its own --user unit, which needs no sudo);
    // otherwise tell the user the exact command to run.
    $ws_manual = $is_root ? 'sudo systemctl restart glommer-websocket' : 'systemctl --user restart glommer-websocket';

    if (trim((string) shell_exec('command -v systemctl 2>/dev/null')) === '') {
        warn('Restart the WebSocket server so it loads the new WS_SECRET (no systemctl found - use your process manager).');
    } else {
        exec('systemctl ' . ($is_root ? '' : '--user ') . 'restart glommer-websocket.service 2>&1', $ws_output, $ws_exit);

        if ($ws_exit === 0) {
            ok('Restarted the WebSocket server so it picks up the new WS_SECRET.');
        } else {
            warn('Could not restart the WebSocket server automatically (' . trim(implode(' ', $ws_output)) . ') - restart it yourself so it loads the new WS_SECRET: ' . $ws_manual);
        }
    }

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

// Under a root install, if HTTPS isn't live yet, try to obtain the web
// certificate automatically now that the hostname is set - install and run
// certbot for whichever web server is running - then re-check. Best-effort: it
// only ever warns on failure, never hard-fails here.
if ($is_root && $https_serving !== true) {
    if (attempt_web_certificate($site_host, (string) $config['mailFromAddress'])) {
        $https_serving = EnvironmentChecker::httpsServing($server_name_value);
    }
}

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

// A fresh database (none of the app's tables yet) gets the current schema
// created directly and skips the incremental upgrade steps (drift, type
// migrations, data backfills) below - those only apply to an already-installed
// database. Computed before any table is created. An empty-but-installed DB
// (tables present, no rows) is NOT fresh and takes the full upgrade path.
$fresh_install = SchemaInstaller::isFreshInstall($mysqli);

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

// Foreign keys are pulled out of the drift here and applied in section 7, after
// the index/type migrations. On an old database a column a new FK references may
// still be a signed int(11) while the key it points at is unsigned; creating the
// FK then fails (errno 150). The type migrations in section 7 unsign those
// columns, so the FKs must wait until after them.
$deferred_foreign_keys = [];
$drift = $fresh_install ? [] : SchemaInstaller::missingDefinitions($mysqli);

if ($fresh_install) {
    ok('fresh install - current schema created directly, no drift to reconcile');
} elseif ($drift === []) {
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
                // Defer foreign keys to section 7 (after the type migrations).
                if (str_starts_with($label, 'foreign key ')) {
                    $deferred_foreign_keys[$table . ': ' . $label] = $alter;

                    continue;
                }

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

// Data backfills only for an already-installed database - a fresh one has no
// legacy rows to convert.
if ($fresh_install) {
    ok('fresh install - no data backfills needed');
} else {
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
}

// schema.sql also carries a handful of idempotent index migrations (ALTER
// TABLE ... ADD/DROP INDEX IF NOT EXISTS/IF EXISTS) - DDL, so unlike the
// UPDATE above these need admin privileges the runtime account deliberately
// doesn't have. Only reach for admin credentials when one is actually still
// needed (an already-applied migration is a no-op and shouldn't force a
// prompt on every healthy re-run - same principle as the schema drift step).
$needed_index_migrations = $fresh_install ? [] : SchemaInstaller::neededIndexMigrations($mysqli);

if ($fresh_install) {
    ok('fresh install - no index/type migrations to apply');
} elseif ($needed_index_migrations === [] && $deferred_foreign_keys === []) {
    ok('index migrations up to date (nothing to apply)');
} else {
    $pending_count = count($needed_index_migrations) + count($deferred_foreign_keys);
    $index_admin_mysqli = admin_connection($config, 'apply ' . $pending_count . ' pending migration(s) from schema.sql');

    if ($index_admin_mysqli === null) {
        fail(
            $pending_count . ' migration(s) from schema.sql are still pending: '
            . implode('; ', array_merge($needed_index_migrations, array_values($deferred_foreign_keys))) . '. '
            . 'Apply them as a MySQL admin, or set DB_ADMIN_USERNAME/DB_ADMIN_PASSWORD and re-run.'
        );
    }

    // Index/type migrations first - these unsign the old signed-int id columns.
    foreach ($needed_index_migrations as $statement) {
        try {
            mysqli_query($index_admin_mysqli, $statement);
            ok('applied: ' . $statement);
        } catch (\mysqli_sql_exception $exception) {
            fail('Failed to apply index migration (' . $statement . '): ' . $exception -> getMessage());
        }
    }

    // Then the foreign keys deferred from section 5, now that the columns they
    // reference are unsigned and the FK can actually be created.
    foreach ($deferred_foreign_keys as $label => $alter) {
        try {
            mysqli_query($index_admin_mysqli, $alter);
            ok('applied ' . $label);
        } catch (\mysqli_sql_exception $exception) {
            fail('Failed to apply foreign key (' . $label . '): ' . $exception -> getMessage());
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
// A sudo run manages the daemons as SYSTEM units; an unprivileged run as
// user-level units - so the restart command differs.
$ws_restart_command = $is_root ? 'sudo systemctl restart glommer-websocket' : 'systemctl --user restart glommer-websocket';
echo "  1. If .env was just created, restart the WebSocket server so it picks up the fresh\n";
echo "     WS_SECRET: " . $ws_restart_command . "\n";
echo "  2. Visit " . $config['siteURL'] . " and sign up - the first account created becomes\n";
echo "     the site's administrator.\n";

// Anything deferred at the environment gate (a fresh install couldn't back up a
// database that didn't exist yet, nor stand up its daemons) was skipped, not
// resolved - the site is now configured, so a second pass can actually check
// and fix them. Surface them so they don't silently stay unmet.
if ($deferred_failures !== []) {
    echo "\n";
    warn(count($deferred_failures) . ' check(s) were deferred because the site was not yet configured - now that it is, re-run "php bin/install.php" to verify and set them up:');

    foreach ($deferred_failures as $name => $message) {
        echo "     - " . $name . "\n";
    }
}
