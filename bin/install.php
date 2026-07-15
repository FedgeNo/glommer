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
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

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

/**
 * Word-wraps authored prose to 80 columns for terminal output, indenting
 * every continuation line by $indent so it lines up under the first line's
 * text (past a status prefix like "[WARN] "). Any whitespace already in
 * $text - including plain newlines from writing it as a free-flowing
 * multi-line string literal in the source, rather than threading `.`
 * concatenation through manual line breaks - collapses to single spaces
 * first, so the source can be formatted however reads best and this always
 * re-wraps it correctly regardless. wordwrap()'s cut=false means a single
 * unbreakable token longer than the remaining width (a URL, a path, a
 * command) is left on its own line rather than mangled. Content that
 * legitimately comes from somewhere else - a run() command/output/exit
 * code, a raw path - is never passed through this; command_context() prints
 * those verbatim.
 */
function wrap(string $text, int $indent = 0): string
{
    $width = max(20, 80 - $indent);
    $collapsed = preg_replace('/\s+/', ' ', trim($text));

    return str_replace("\n", "\n" . str_repeat(' ', $indent), wordwrap($collapsed, $width, "\n", false));
}

function ok(string $message): void
{
    echo color('[ OK ]', '32') . ' ' . wrap($message, 7) . "\n";
}

/**
 * $result, when given, is a run() return value - the exact command that was
 * run and what it printed, shown indented under the message. Every call site
 * decides for itself whether a given command's result counts as a failure
 * (a non-zero exit is the normal, silently-handled result for plenty of
 * commands here - "command -v mkcert" failing just means falling back to a
 * different path, not that anything's wrong) - warn()/fail_line() only ever
 * show a command's output when a caller has already decided something IS
 * wrong and is reporting it, never automatically off the exit code alone.
 */
function warn(string $message, ?array $result = null): void
{
    echo color('[WARN]', '33') . ' ' . wrap($message, 7) . "\n";
    echo command_context($result);
}

function fail_line(string $message, ?array $result = null): void
{
    echo color('[FAIL]', '31') . ' ' . wrap($message, 7) . "\n";
    echo command_context($result);
}

function fail(string $message, ?array $result = null): never
{
    fail_line($message, $result);
    exit(1);
}

function heading(string $text): void
{
    echo "\n" . color($text, '1') . "\n";
}

/** Formats a run() result for warn()/fail_line() - '' (nothing printed) when $result is null. */
function command_context(?array $result): string
{
    if ($result === null) {
        return '';
    }

    $lines = ['       $ ' . $result['command'], '       exit ' . $result['exitCode']];

    if ($result['output'] !== '') {
        foreach (explode("\n", $result['output']) as $output_line) {
            $lines[] = '       ' . $output_line;
        }
    }

    return implode("\n", $lines) . "\n";
}

// ---------- Shell execution availability ----------

// Checked before anything else: certbot, systemctl, mysqldump, package
// managers, mkcert, ffmpeg detection - essentially this whole installer -
// all shell out. Some hardened php.ini profiles (shared hosting especially)
// disable exec/shell_exec/proc_open via disable_functions; without this
// check that shows up as a long tail of individually confusing failures
// (a "command not found"-shaped error for every single downstream check)
// instead of one clear diagnosis up front.
if (!function_exists('shell_exec') || !function_exists('exec') || !function_exists('proc_open')) {
    $disabled = array_filter(array_map('trim', explode(',', (string) ini_get('disable_functions'))));

    fail('This installer needs shell execution (exec/shell_exec/proc_open), which PHP has disabled here'
        . ($disabled !== [] ? ' (disable_functions = ' . implode(', ', $disabled) . ')' : '')
        . '. It shells out to systemctl, certbot, mysqldump, package managers, and more throughout - '
        . 'nothing here works without it. Re-enable those three functions in php.ini (remove them from '
        . 'disable_functions) and re-run, or provision this server by hand (see README.md).');
}

/**
 * Runs a shell command and returns its exit code, combined stdout+stderr,
 * and the command itself - without printing anything, same as the raw
 * shell_exec()/exec() calls this replaces throughout the file. $command
 * should redirect its own stderr as needed (2>&1 to capture it, 2>/dev/null
 * to discard it) exactly as the old direct calls did - run() doesn't impose
 * a redirection of its own, so a caller that wants clean output for string
 * comparison (e.g. `systemctl is-active ... 2>/dev/null`) still gets it.
 * 'output' is trimmed (leading/trailing whitespace only - internal lines are
 * untouched) since nearly every caller immediately does its own trim() on
 * what used to be a raw shell_exec() string; doing it once here means call
 * sites don't have to.
 *
 * $args, when given, is a name => value map: every ':name' token in
 * $command is replaced with escapeshellarg($value) before running it - e.g.
 * run('systemctl restart :unit 2>&1', ['unit' => $unit]) instead of
 * building the string by hand with escapeshellarg() calls threaded through
 * concatenation. Shell syntax (redirects, `||`, `env VAR=...`) stays as
 * plain text in $command exactly as before; only the dynamic values go
 * through $args. A value that's itself an array (e.g. a variable-length
 * list of file paths) is escaped element-by-element and joined with spaces,
 * for the handful of commands that take a variadic list of arguments.
 *
 * Callers decide what a given command's result means - a non-zero exit is
 * the normal, silently-handled outcome for plenty of these (an existence
 * check that just falls back to something else), not automatically a
 * failure - so run() itself never prints. When a caller DOES decide the
 * result means something's wrong, pass this return value to warn()/
 * fail_line()/fail() so the exact command and what it printed show up as
 * part of that message.
 */
function run(string $command, array $args = []): array
{
    foreach ($args as $name => $value) {
        $escaped = is_array($value)
            ? implode(' ', array_map('escapeshellarg', $value))
            : escapeshellarg((string) $value);

        // preg_replace_callback, not preg_replace: the replacement is
        // inserted literally, with no $1/\1-style backreference
        // interpretation - a plain preg_replace() would mangle a value
        // containing a literal "$" followed by digits (e.g. a path with
        // "$1" in it).
        $command = preg_replace_callback('/:' . preg_quote((string) $name, '/') . '\b/', fn () => $escaped, $command);
    }

    exec($command, $output_lines, $exit_code);

    // rtrim() per line - not trim() - so a stray trailing \r or trailing
    // spaces on an interior line don't survive, without stripping leading
    // indentation some commands (systemctl status, journalctl) use
    // meaningfully. The outer trim() then drops leading/trailing blank lines.
    return [
        'command' => $command,
        'exitCode' => $exit_code,
        'output' => trim(implode("\n", array_map('rtrim', $output_lines))),
    ];
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
    $stty_available = run('command -v stty 2>/dev/null')['output'] !== '';

    if ($stty_available) {
        run('stty -echo');
    }

    echo $label . ': ';
    $line = fgets(STDIN);

    if ($stty_available) {
        run('stty echo');
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
    if (!user_systemd_available()) {
        return false;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        warn('Could not resolve a home directory for the current user - cannot place a user-level systemd unit. See README.md.');

        return false;
    }

    $unit_path = $home . '/.config/systemd/user/glommer-websocket.service';

    echo "\n" . wrap("The WebSocket server isn't running. It can be installed as a user-level
    systemd service (no root needed) at $unit_path", 0) . "\n";

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
    if (!user_systemd_available()) {
        return;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        return;
    }

    $unit_path = $home . '/.config/systemd/user/glommer-websocket.service';

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

    run('systemctl --user daemon-reload 2>&1');

    $was_active = run('systemctl --user is-active glommer-websocket.service 2>/dev/null')['output'] === 'active';

    if ($was_active) {
        run('systemctl --user restart glommer-websocket.service 2>&1');
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
    if (!user_systemd_available()) {
        warn('No usable systemctl --user session (missing systemctl, or no user D-Bus session reachable from this context - e.g. a non-interactive SSH command or `sudo -u` invocation) - run bin/websocket-server.php under your own process manager, or set this up from a real login session. See README.md.');

        return false;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        warn('Could not resolve a home directory for the current user - cannot place a user-level systemd unit. See README.md.');

        return false;
    }

    $unit_path = $home . '/.config/systemd/user/glommer-websocket.service';
    $unit_contents = websocket_unit_contents();

    if (!is_dir(dirname($unit_path)) && !@mkdir(dirname($unit_path), 0755, true)) {
        fail_line('Could not create ' . dirname($unit_path) . ' - create the unit manually (see README.md).');

        return false;
    }

    if (file_put_contents($unit_path, $unit_contents) === false) {
        fail_line('Could not write ' . $unit_path . ' - create the unit manually (see README.md).');

        return false;
    }

    run('systemctl --user daemon-reload 2>&1');
    $enable_result = run('systemctl --user enable --now glommer-websocket.service 2>&1');

    // Give the daemon a moment to bind its ports before re-checking.
    sleep(1);

    $status = run('systemctl --user is-active glommer-websocket.service 2>/dev/null')['output'];

    if ($status !== 'active') {
        fail_line('The service was written but did not start. Check: systemctl --user status glommer-websocket', $enable_result);

        return false;
    }

    ok('WebSocket service installed and started (' . $unit_path . ')');

    // A user-level service only keeps running after the admin logs out (and
    // only starts on boot) if that user has "lingering" enabled - otherwise
    // the daemon dies the moment whoever ran the installer disconnects, which
    // is exactly the headless-server case. Enable it now (allowed for your own
    // user without root on most systems) so the daemon truly runs unattended.
    $user = run('id -un 2>/dev/null')['output'];

    if ($user === '') {
        $user = get_current_user() ?: (string) getenv('USER');
    }

    $linger_enable_result = run('loginctl enable-linger :user 2>&1', ['user' => $user]);
    $linger_output = strtolower(run('loginctl show-user :user --property=Linger 2>/dev/null', ['user' => $user])['output']);

    if (str_contains($linger_output, 'yes')) {
        ok('Lingering enabled for ' . $user . ' - the daemon keeps running after logout and starts on boot.');
    } else {
        warn('Could not enable lingering for ' . $user . ' automatically. The daemon will stop when this user'
            . ' logs out (and won\'t start on boot) until you run: sudo loginctl enable-linger ' . $user, $linger_enable_result);
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
    echo "\n" . wrap("The WebSocket daemon is reachable right now, but glommer-websocket.service
    isn't enabled (or lingering isn't) - it won't survive a restart or reboot as-is.", 0) . "\n";

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
    if (!user_systemd_available()) {
        return;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        return;
    }

    $unit_path = $home . '/.config/systemd/user/glommer-upload-worker.service';

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

    run('systemctl --user daemon-reload 2>&1');

    $was_active = run('systemctl --user is-active glommer-upload-worker.service 2>/dev/null')['output'] === 'active';

    if ($was_active) {
        run('systemctl --user restart glommer-upload-worker.service 2>&1');
    }

    ok('Upload worker service unit updated to the current template' . ($was_active ? ' and the service restarted' : ''));
}

/**
 * Writes the upload-worker service's unit file, enables it to start now and on
 * boot, and sets up lingering. Mirrors write_and_enable_websocket_service().
 */
function write_and_enable_upload_worker_service(): bool
{
    if (!user_systemd_available()) {
        warn('No usable systemctl --user session (missing systemctl, or no user D-Bus session reachable from this context - e.g. a non-interactive SSH command or `sudo -u` invocation) - run bin/upload-worker.php under your own process manager, or set this up from a real login session. See README.md.');

        return false;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        warn('Could not resolve a home directory for the current user - cannot place a user-level systemd unit. See README.md.');

        return false;
    }

    $unit_path = $home . '/.config/systemd/user/glommer-upload-worker.service';
    $unit_contents = upload_worker_unit_contents();

    if (!is_dir(dirname($unit_path)) && !@mkdir(dirname($unit_path), 0755, true)) {
        fail_line('Could not create ' . dirname($unit_path) . ' - create the unit manually (see README.md).');

        return false;
    }

    if (file_put_contents($unit_path, $unit_contents) === false) {
        fail_line('Could not write ' . $unit_path . ' - create the unit manually (see README.md).');

        return false;
    }

    run('systemctl --user daemon-reload 2>&1');
    $enable_result = run('systemctl --user enable --now glommer-upload-worker.service 2>&1');

    sleep(1);

    $status = run('systemctl --user is-active glommer-upload-worker.service 2>/dev/null')['output'];

    if ($status !== 'active') {
        fail_line('The service was written but did not start. Check: systemctl --user status glommer-upload-worker', $enable_result);

        return false;
    }

    ok('Upload worker service installed and started (' . $unit_path . ')');

    $user = run('id -un 2>/dev/null')['output'];

    if ($user === '') {
        $user = get_current_user() ?: (string) getenv('USER');
    }

    $linger_enable_result = run('loginctl enable-linger :user 2>&1', ['user' => $user]);
    $linger_output = strtolower(run('loginctl show-user :user --property=Linger 2>/dev/null', ['user' => $user])['output']);

    if (str_contains($linger_output, 'yes')) {
        ok('Lingering enabled for ' . $user . ' - the worker keeps running after logout and starts on boot.');
    } else {
        warn('Could not enable lingering for ' . $user . ' automatically. The worker will stop when this user'
            . ' logs out (and won\'t start on boot) until you run: sudo loginctl enable-linger ' . $user, $linger_enable_result);
    }

    return true;
}

/**
 * When the upload-worker persistence check failed - the service isn't enabled
 * (staged uploads would queue forever) - offer to install and enable it.
 */
function offer_enable_upload_worker_service(): bool
{
    echo "\n" . wrap("The media upload-worker service (glommer-upload-worker.service) isn't enabled -
    without it, staged video/audio uploads are never transcoded (they queue forever).", 0) . "\n";

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

    echo "\n" . wrap('It can also run automatically every night via a user-level systemd timer (no root needed).') . "\n";

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
    if (!user_systemd_available()) {
        return;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        return;
    }

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

    run('systemctl --user daemon-reload 2>&1');
    ok('Backup timer units updated to the current template');
}

function write_and_enable_backup_timer(): void
{
    if (!user_systemd_available()) {
        warn('No usable systemctl --user session (missing systemctl, or no user D-Bus session reachable from this context - e.g. a non-interactive SSH command or `sudo -u` invocation) - set up a recurring backup yourself (cron or otherwise), or run this from a real login session. See README.md\'s Backups section.');

        return;
    }

    $home = resolve_home_dir();

    if ($home === null) {
        warn('Could not resolve a home directory for the current user - cannot place a user-level systemd unit. See README.md.');

        return;
    }

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

    run('systemctl --user daemon-reload 2>&1');
    $enable_result = run('systemctl --user enable --now glommer-backup.timer 2>&1');

    $status = run('systemctl --user is-enabled glommer-backup.timer 2>/dev/null')['output'];

    if ($status !== 'enabled') {
        fail_line('The timer was written but is not enabled. Check: systemctl --user status glommer-backup.timer', $enable_result);

        return;
    }

    ok('Nightly backup timer installed and enabled (' . $timer_path . ')');

    // A user-level timer only survives logout/reboot with lingering enabled.
    $user = run('id -un 2>/dev/null')['output'];

    if ($user === '') {
        $user = get_current_user() ?: (string) getenv('USER');
    }

    $linger_enable_result = run('loginctl enable-linger :user 2>&1', ['user' => $user]);
    $linger_output = strtolower(run('loginctl show-user :user --property=Linger 2>/dev/null', ['user' => $user])['output']);

    if (str_contains($linger_output, 'yes')) {
        ok('Lingering enabled for ' . $user . ' - the timer keeps firing after logout and across reboots.');
    } else {
        warn('Could not enable lingering for ' . $user . ' automatically. The timer won\'t fire after logout'
            . ' (or survive a reboot) until you run: sudo loginctl enable-linger ' . $user, $linger_enable_result);
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
    echo "\n" . wrap('The nightly backup timer isn\'t enabled (or lingering isn\'t, so it wouldn\'t survive logout/reboot even if it were).') . "\n";

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
function offer_websocket_tls(string $host, bool $refresh = false): bool
{
    $mkcert_available = run('command -v mkcert 2>/dev/null')['output'] !== '';

    if (!$mkcert_available) {
        return false;
    }

    $home = resolve_mkcert_home_dir();

    if ($home === null) {
        warn('Could not resolve a home directory for the current user - cannot place a mkcert certificate. See README.md.');

        return false;
    }

    $cert_dir = $home . '/.local/share/glommer-certs';
    $cert_path = $cert_dir . '/' . $host . '.pem';
    $key_path = $cert_dir . '/' . $host . '-key.pem';
    $mkcert_caroot = $home . '/.local/share/mkcert';

    // A $refresh pass runs every time (see the call site) precisely because an
    // already-configured WS_TLS_CERT doesn't mean it's still correct - SITE_URL
    // can change hostname without that check ever noticing the old cert no
    // longer matches. So this skips the "no cert configured yet" framing and
    // the confirm() prompt: regenerating for the current host is cheap and
    // idempotent, and asking every single run would just be friction.
    if ($refresh) {
        echo "\n" . wrap('Re-checking the WebSocket daemon\'s mkcert certificate covers ' . $host . '...') . "\n";
    } else {
        echo "\n" . wrap("The WebSocket daemon has no TLS certificate configured. mkcert is available -
        it can generate one for $host at $cert_dir (Browsers only trust it without a warning
        if \"mkcert -install\" has been run on this machine - a one-time step, needs sudo
        the first time, separate from this.)") . "\n";

        if (!confirm('Generate a certificate now and configure it?')) {
            return false;
        }
    }

    $cert_dir_created = !is_dir($cert_dir);

    if ($cert_dir_created && !@mkdir($cert_dir, 0700, true)) {
        fail_line('Could not create ' . $cert_dir . ' - create it manually and set WS_TLS_CERT/WS_TLS_KEY in .env.');

        return false;
    }

    // env -u SUDO_ASKPASS, same reasoning as web_user_can_read()/
    // user_systemctl(): mkcert can shell out to sudo itself (e.g. checking/
    // installing the CA into a system or NSS trust store), and exec()'s child
    // has no tty on stdin, so an inherited SUDO_ASKPASS would make any of
    // that prefer a graphical prompt over the real terminal - a no-go over
    // SSH. CAROOT is set explicitly to $home's mkcert CA (see
    // resolve_mkcert_home_dir()'s docblock) rather than trusting mkcert's own
    // default resolution, which goes by the process's actual HOME/euid - root
    // under sudo, not the human whose browser needs to trust the result.
    $mkcert_result = run(
        'env -u SUDO_ASKPASS CAROOT=:caroot mkcert -cert-file :cert -key-file :key :host 2>&1',
        ['caroot' => $mkcert_caroot, 'cert' => $cert_path, 'key' => $key_path, 'host' => $host]
    );

    if ($mkcert_result['exitCode'] !== 0 || !is_file($cert_path) || !is_file($key_path)) {
        fail_line('mkcert failed.', $mkcert_result);

        return false;
    }

    if (!EnvironmentChecker::webSocketCertificateAndKeyMatch($cert_path, $key_path)) {
        fail_line('mkcert reported success, but the generated certificate and key don\'t actually match - not using them. Generate a certificate manually (see README.md\'s HTTPS section) and set WS_TLS_CERT/WS_TLS_KEY in .env.');

        return false;
    }

    // Running as root wrote these as root, into what's otherwise the sudo
    // invoker's own home dir (resolve_mkcert_home_dir()) - hand them back so
    // an unprivileged run later doesn't trip over root-owned files it can't
    // touch in its own home directory.
    if (running_as_root()) {
        $owner = app_service_user();

        if ($owner !== null) {
            if ($cert_dir_created) {
                @chown($cert_dir, $owner);
                @chgrp($cert_dir, $owner);
            }

            @chown($cert_path, $owner);
            @chgrp($cert_path, $owner);
            @chown($key_path, $owner);
            @chgrp($key_path, $owner);
        }
    }

    $env_path = __DIR__ . '/../.env';
    $env_contents = (string) file_get_contents($env_path);
    // preg_replace_callback, not preg_replace: the callback's return value is
    // inserted literally, with no $1/\1-style backreference interpretation of
    // the replacement text - a plain preg_replace() would mangle a cert/key
    // path containing a literal "$" followed by digits (e.g. an un-expanded
    // "$HOME" left in the path).
    $env_contents = preg_replace_callback('/^WS_TLS_CERT=.*$/m', fn () => 'WS_TLS_CERT=' . $cert_path, $env_contents, -1, $cert_replaced);

    // Older .env files predate these keys - append when there's no line to
    // replace rather than making the admin add it by hand.
    if ($cert_replaced === 0) {
        $env_contents = rtrim($env_contents, "\n") . "\nWS_TLS_CERT=" . $cert_path . "\n";
    }

    $env_contents = preg_replace_callback('/^WS_TLS_KEY=.*$/m', fn () => 'WS_TLS_KEY=' . $key_path, $env_contents, -1, $key_replaced);

    if ($key_replaced === 0) {
        $env_contents = rtrim($env_contents, "\n") . "\nWS_TLS_KEY=" . $key_path . "\n";
    }

    if (file_put_contents($env_path, $env_contents) === false) {
        fail_line('Could not write .env.');

        return false;
    }

    putenv('WS_TLS_CERT=' . $cert_path);
    putenv('WS_TLS_KEY=' . $key_path);

    ok('.env updated with WS_TLS_CERT/WS_TLS_KEY');

    // A root install's daemon is a SYSTEM unit, never a --user one - trying
    // `systemctl --user restart` here would always be a guaranteed no-op
    // ("Unit ... not found"), not a real attempt. relocate_ws_cert_if_root()
    // (called right after this by both of this function's callers) does the
    // real, correctly-scoped restart in that case, so this whole block is
    // skipped rather than reporting a failure that was never a real attempt.
    if (!running_as_root()) {
        if (user_systemd_available()) {
            $restart_result = run('systemctl --user restart glommer-websocket.service 2>&1');
            sleep(1);

            $status = run('systemctl --user is-active glommer-websocket.service 2>/dev/null')['output'];

            if ($status === 'active') {
                ok('WebSocket daemon restarted with the new certificate');
            } else {
                warn('Could not confirm the daemon restarted - check: systemctl --user status glommer-websocket', $restart_result);
            }
        } else {
            warn('Restart the WebSocket daemon manually to pick up the new certificate (no user systemd session reachable right now - run "systemctl --user restart glommer-websocket" from a real login session).');
        }
    }

    return true;
}

/**
 * Zero-touch WebSocket TLS for a root install: reuse whatever certificate the
 * web server already serves (self-signed or real - doesn't matter, the point is
 * the wss:// blocker clears itself with no manual steps). Discovers the live
 * cert/key from the running web server's config, points WS_TLS_CERT/WS_TLS_KEY
 * at them, makes them readable by the daemon's user, and restarts the daemon.
 * Returns false (so the caller falls back to the interactive offer / fail) when
 * it isn't root, the web server or its cert can't be found, or the cert and key
 * don't match.
 */
function configure_websocket_tls_from_web_server(string $host): bool
{
    if (!running_as_root()) {
        return false;
    }

    $server = running_web_server();
    $cert = null;
    $key = null;

    if ($server === 'apache') {
        // Apache is last-wins, so scan every SSLCertificateFile/KeyFile across
        // the config tree and keep the last of each. The anchored grep matches
        // only lines whose first non-whitespace token IS the directive, so a
        // commented '#SSLCertificateFile ...' line never matches - no separate
        // comment stripping needed.
        $lines = explode("\n", run('grep -rEhi "^[[:space:]]*SSLCertificate(File|KeyFile)[[:space:]]" /etc/httpd /etc/apache2 2>/dev/null')['output']);

        foreach ($lines as $line) {
            $line = trim($line);

            // KeyFile before File - "SSLCertificateFile" is a prefix of
            // "SSLCertificateKeyFile", so test the longer name first.
            if (preg_match('/^SSLCertificateKeyFile\s+(.+)$/i', $line, $matches) === 1) {
                $key = trim($matches[1], " \t\"'");
            } elseif (preg_match('/^SSLCertificateFile\s+(.+)$/i', $line, $matches) === 1) {
                $cert = trim($matches[1], " \t\"'");
            }
        }
    } elseif ($server === 'nginx') {
        $lines = explode("\n", run('grep -rEhi "^[[:space:]]*ssl_certificate(_key)?[[:space:]]" /etc/nginx 2>/dev/null')['output']);

        foreach ($lines as $line) {
            $line = trim($line);

            if (preg_match('/^ssl_certificate_key\s+([^;]+);/i', $line, $matches) === 1) {
                $key = trim($matches[1], " \t\"'");
            } elseif (preg_match('/^ssl_certificate\s+([^;]+);/i', $line, $matches) === 1) {
                $cert = trim($matches[1], " \t\"'");
            }
        }
    } else {
        return false;
    }

    if (!is_string($cert) || $cert === '' || !is_string($key) || $key === '' || !is_file($cert) || !is_file($key)) {
        return false;
    }

    if (!EnvironmentChecker::webSocketCertificateAndKeyMatch($cert, $key)) {
        return false;
    }

    heading('WebSocket TLS');

    $env_path = __DIR__ . '/../.env';
    $env_contents = (string) file_get_contents($env_path);
    // preg_replace_callback (not preg_replace) so a cert path containing a
    // literal "$1"-style sequence isn't treated as a backreference - same
    // reasoning as offer_websocket_tls().
    $env_contents = preg_replace_callback('/^WS_TLS_CERT=.*$/m', fn () => 'WS_TLS_CERT=' . $cert, $env_contents, -1, $cert_replaced);

    // Older .env files predate these keys, so there may be no line to replace -
    // append it rather than making the admin add it by hand.
    if ($cert_replaced === 0) {
        $env_contents = rtrim($env_contents, "\n") . "\nWS_TLS_CERT=" . $cert . "\n";
    }

    $env_contents = preg_replace_callback('/^WS_TLS_KEY=.*$/m', fn () => 'WS_TLS_KEY=' . $key, $env_contents, -1, $key_replaced);

    if ($key_replaced === 0) {
        $env_contents = rtrim($env_contents, "\n") . "\nWS_TLS_KEY=" . $key . "\n";
    }

    if (file_put_contents($env_path, $env_contents) === false) {
        fail_line('Could not write .env.');

        return false;
    }

    putenv('WS_TLS_CERT=' . $cert);
    putenv('WS_TLS_KEY=' . $key);

    ok('Configured WebSocket TLS to reuse the web server certificate (' . $cert . ')');

    // The daemon runs as the web-server user (the same account
    // set_up_system_services picks). mod_ssl / Let's Encrypt keys are typically
    // root-only, so make sure that user can read the cert - ensure_ws_cert_readable
    // relocates them to /etc/glommer, fixes perms, repoints .env, and restarts the
    // daemon when it has to.
    $web = web_server_account();
    $service_user = $web !== null ? $web['user'] : (env_file_owner() ?? app_service_user());

    if ($service_user === null) {
        warn('Could not determine which user the WebSocket daemon runs as - .env now points at the web server\'s certificate, but the daemon may not be able to read it (mod_ssl/Let\'s Encrypt keys are often root-only). If wss:// fails, relocate the cert somewhere that user can read and repoint WS_TLS_CERT/WS_TLS_KEY.');

        return true;
    }

    ensure_ws_cert_readable($service_user, $web);

    // ensure_ws_cert_readable only restarts the daemon when it had to relocate an
    // unreadable cert (it repoints WS_TLS_CERT to /etc/glommer then). If it no-op'd
    // (cert already readable), WS_TLS_CERT is still the path we just set, and the
    // daemon is running on the old .env - restart it here so it picks up the new
    // WS_TLS_*.
    if (getenv('WS_TLS_CERT') === $cert
        && run('systemctl is-active glommer-websocket.service 2>/dev/null')['output'] === 'active') {
        run('systemctl restart glommer-websocket.service 2>&1');
        ok('WebSocket daemon restarted to pick up the new TLS certificate');
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
    return run('id -u 2>/dev/null')['output'] === '0';
}

/**
 * Offers to re-exec this same script under sudo when it isn't running as
 * root yet and a real terminal can answer the prompt. Root unlocks real
 * system units (survive logout/reboot with no lingering workaround needed),
 * auto-installing missing prerequisite packages, and relocating TLS certs
 * to a location every relevant account can read - substantial enough to be
 * worth asking for, but never required: every one of those has an
 * unprivileged fallback already (user-level systemd units, printed manual
 * install commands, a cert left wherever mkcert put it) - declining, or a
 * non-interactive run, just continues as the current user exactly as before
 * this existed. `sudo` inherits this process's real terminal via passthru()
 * (not exec()/shell_exec(), which don't), so its password prompt appears
 * normally and re-exec picks up right where this process left off.
 */
function offer_root_reexec(): void
{
    if (running_as_root() || !is_interactive()) {
        return;
    }

    if (run('command -v sudo 2>/dev/null')['output'] === '') {
        return;
    }

    echo "\n" . wrap("This installer works best run as root (via sudo): it can install real system
    services (no lingering workaround needed to survive logout or reboot), auto-install
    missing prerequisite packages, and relocate TLS certs to a location every relevant
    account can read. It still works without root - user-level systemd services, printed
    manual install commands - just with more manual follow-up.") . "\n";

    if (!confirm('Re-run with sudo now?')) {
        return;
    }

    $arg_string = '';

    foreach (array_slice($_SERVER['argv'] ?? [], 1) as $arg) {
        $arg_string .= ' ' . escapeshellarg($arg);
    }

    passthru('sudo ' . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . $arg_string, $reexec_code);
    exit($reexec_code);
}

/**
 * Whether `systemctl --user` can actually reach a user systemd instance right
 * now. It needs a per-user D-Bus session bus, normally reached via
 * XDG_RUNTIME_DIR/systemd/private from a real login session - which a
 * non-interactive SSH command, a `sudo -u someuser ...` invocation, or cron
 * frequently doesn't have. Every write-and-enable/reconcile function for the
 * user-level services (websocket, upload-worker, backup timer) shells out
 * to `systemctl --user` assuming this works; without checking first, a
 * missing session bus previously surfaced as a generic "the service was
 * written but did not start" - true, but pointing at the wrong cause
 * entirely (the unit is fine; there's just nothing to talk to).
 */
function user_systemd_available(): bool
{
    if (run('command -v systemctl 2>/dev/null')['output'] === '') {
        return false;
    }

    $runtime_dir = getenv('XDG_RUNTIME_DIR');

    if (is_string($runtime_dir) && $runtime_dir !== '' && file_exists($runtime_dir . '/systemd/private')) {
        return true;
    }

    // XDG_RUNTIME_DIR not visible to this process (a different su/sudo path
    // can lose it even when a real session exists) - fall back to a live
    // probe rather than trusting the environment variable alone.
    $probe = run('systemctl --user is-system-running 2>&1')['output'];

    return !str_contains($probe, 'Failed to connect to bus');
}

/**
 * Restarts the WebSocket and upload-worker daemons - whichever are already
 * running, as a system unit or a leftover not-yet-migrated user-level one -
 * before the environment checks below test them, not after. A long-running
 * daemon keeps executing whatever code/config was current when it started,
 * not what's on disk now: this bit us for real once (the upload worker ran
 * for almost a day on a stale autoloader path after a directory rename,
 * silently failing every claim attempt while `systemctl is-active` said
 * active the whole time). Testing without restarting first only confirms a
 * daemon is alive, never that it's running what's actually on disk. Every
 * write path that changes something a daemon depends on (.env, its unit
 * file, its TLS cert) already restarts it afterward on its own - this
 * covers the other direction: a daemon that's been running since well
 * before this invocation, on code that changed since. Best-effort and
 * silent about anything not installed/running yet - offer_websocket_
 * service() etc. bring those up for the first time; this only refreshes
 * ones already going.
 */
function restart_already_running_daemons(): void
{
    $is_root = running_as_root();
    $units = ['glommer-websocket.service', 'glommer-upload-worker.service'];

    if ($is_root) {
        foreach ($units as $unit) {
            if (run('systemctl is-active :unit 2>/dev/null', ['unit' => $unit])['output'] === 'active') {
                run('systemctl restart :unit 2>&1', ['unit' => $unit]);
            }
        }

        // A root run hasn't reached set_up_system_services() yet at this
        // point (it runs after these checks) - if this box was previously
        // set up unprivileged, what's actually live right now is still a
        // user-level unit under the likely service account, not a system one.
        $candidate = app_service_user();

        if ($candidate !== null) {
            foreach ($units as $unit) {
                if (user_systemctl($candidate, 'is-active :unit', ['unit' => $unit]) === 'active') {
                    user_systemctl($candidate, 'restart :unit', ['unit' => $unit]);
                }
            }
        }
    } elseif (user_systemd_available()) {
        foreach ($units as $unit) {
            if (run('systemctl --user is-active :unit 2>/dev/null', ['unit' => $unit])['output'] === 'active') {
                run('systemctl --user restart :unit 2>&1', ['unit' => $unit]);
            }
        }
    }

    // Give both a moment to finish binding their ports/claiming lock files
    // before the checks below probe them.
    usleep(500000);
}

/**
 * The home directory whose mkcert CA should be used for a cert mkcert
 * generates on this install's behalf - NOT necessarily resolve_home_dir()'s
 * answer. Under `sudo php bin/install.php`, resolve_home_dir() correctly
 * resolves to root's own home (its whole point is being honest about the
 * CURRENT process's identity) - but mkcert auto-creates a fresh, separate CA
 * per CAROOT the first time it's used, and root's CA was never the one
 * "mkcert -install" (or a prior unprivileged run of this installer) added to
 * anyone's browser trust store. A cert signed by it is signed by a CA
 * nothing trusts, no matter how correct its hostname is (a "tlsv1 alert
 * unknown ca" from the browser, not a hostname mismatch). So this resolves
 * to the sudo invoker's home when running as root instead - the actual human
 * whose browser needs to trust the result - falling back to
 * resolve_home_dir() when that can't be determined (e.g. a genuine root
 * install with no SUDO_USER).
 */
function resolve_mkcert_home_dir(): ?string
{
    if (running_as_root()) {
        $owner = app_service_user();

        if ($owner !== null) {
            $line = run('getent passwd :owner 2>/dev/null', ['owner' => $owner])['output'];

            if ($line !== '') {
                $home = explode(':', $line)[5] ?? '';

                if ($home !== '') {
                    return $home;
                }
            }
        }
    }

    return resolve_home_dir();
}

/**
 * The current process's real home directory, resolved authoritatively from
 * /etc/passwd (via posix_getpwuid, then getent as a fallback) rather than
 * trusted from $_SERVER['HOME']/getenv('HOME'). Those environment variables
 * are only as correct as whatever sudoers policy (env_reset,
 * always_set_home) or su/sudo invocation set them - `sudo -u someuser php
 * bin/install.php` doesn't reliably reset HOME to someuser's home across
 * every distro's default config, and every user-level systemd unit path
 * (websocket/upload-worker/backup timer, mkcert cert dir) is built from this
 * value. Getting it wrong doesn't error - it silently writes the unit file
 * into the WRONG user's ~/.config/systemd/user/, where that user's own
 * `systemctl --user` will never find it, so this only ever fell back to the
 * environment as a last resort.
 */
function resolve_home_dir(): ?string
{
    if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
        $info = posix_getpwuid(posix_geteuid());

        if (is_array($info) && isset($info['dir']) && $info['dir'] !== '') {
            return $info['dir'];
        }
    }

    $uid = run('id -u 2>/dev/null')['output'];

    if (ctype_digit($uid)) {
        $line = run('getent passwd :uid 2>/dev/null', ['uid' => $uid])['output'];

        if ($line !== '') {
            $fields = explode(':', $line);

            if (isset($fields[5]) && $fields[5] !== '') {
                return $fields[5];
            }
        }
    }

    $env_home = $_SERVER['HOME'] ?? getenv('HOME');

    return is_string($env_home) && $env_home !== '' ? $env_home : null;
}

/**
 * Maps a numeric uid to a username via getent, then /etc/passwd. Null if neither
 * is available or the uid isn't found (nothing here is assumed present).
 */
function uid_to_username(int $uid): ?string
{
    $line = run('getent passwd :uid 2>/dev/null', ['uid' => (string) $uid])['output'];

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
 * Creates (if missing) a dedicated, unprivileged system account for the
 * WebSocket daemon specifically - kept separate from the web-server account
 * the upload-worker legitimately shares (see fix_upload_ownership()'s
 * docblock: that sharing is about uploads/ ownership, which the WS daemon
 * has no part in). The WS daemon is a hand-rolled WebSocket frame parser
 * directly exposed to untrusted network input - running it as the same
 * account that owns uploads/ and can read .env gives a bug or compromise in
 * it write access to things it has no functional reason to touch. No login
 * shell, no home directory - it only ever needs to read its own TLS cert
 * copy and .env (both granted read-only via group membership). Idempotent:
 * a no-op if the account already exists. Returns the username, or null if
 * creation failed (the caller then falls back to sharing the web-server
 * account, same as before this existed).
 */
function ensure_ws_dedicated_user(): ?string
{
    $username = 'glommer-ws';

    if (run('id -u :username 2>/dev/null', ['username' => $username])['output'] !== '') {
        return $username;
    }

    $useradd = run('command -v useradd 2>/dev/null')['output'];

    if ($useradd === '') {
        warn('No useradd found - cannot create a dedicated system account for the WebSocket daemon. It will run as the web-server user instead.');

        return null;
    }

    $useradd_result = run($useradd . ' --system --no-create-home --shell /sbin/nologin :username 2>&1', ['username' => $username]);

    if ($useradd_result['exitCode'] !== 0) {
        warn('Could not create the dedicated ' . $username . ' system account - the WebSocket daemon will run as the web-server user instead.', $useradd_result);

        return null;
    }

    ok('Created dedicated system account ' . $username . ' for the WebSocket daemon, separate from the web-server user the upload-worker shares.');

    return $username;
}

/**
 * Adds $username as a supplementary member of $group - read-only in
 * practice, since .env and uploads/ are only ever group-readable (0640/0644
 * files, 0755 dirs), never group-writable (see fix_upload_ownership()).
 * Used to give the dedicated WS account read access to .env (for
 * WS_SECRET/WS_PORT/etc.) without granting it any of the web-server
 * account's actual write access to uploads/ or .env itself.
 */
function grant_group_membership(string $username, string $group): void
{
    $usermod = run('command -v usermod 2>/dev/null')['output'];

    if ($usermod === '') {
        warn('No usermod found - cannot add ' . $username . ' to the ' . $group . ' group. It won\'t be able to read .env; the WebSocket daemon will fail to start until this is done manually.');

        return;
    }

    $usermod_result = run($usermod . ' -aG :group :username 2>&1', ['group' => $group, 'username' => $username]);

    if ($usermod_result['exitCode'] !== 0) {
        warn('Could not add ' . $username . ' to the ' . $group . ' group - it won\'t be able to read .env.', $usermod_result);
    }
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

    $gid = run('id -g :user 2>/dev/null', ['user' => $user])['output'];
    $group = null;

    if ($gid !== '' && ctype_digit($gid)) {
        $group_line = run('getent group :gid 2>/dev/null', ['gid' => $gid])['output'];
        $group = $group_line !== '' ? (explode(':', $group_line)[0] ?: null) : null;
    }

    return ['user' => $user, 'group' => $group];
}

/**
 * Runs a `systemctl --user` command inside $user's own systemd instance from
 * this root context - needed to stop and disable the user-level services a prior
 * unprivileged install left running, before system units replace them. Requires
 * sudo and the user's runtime dir; returns '' if either is missing.
 *
 * $systemctl_args is the sub-command after `systemctl --user` (e.g. 'stop
 * :unit') with its own ':name' placeholders, filled from $values the same
 * way run() fills $command's - callers don't escapeshellarg() the unit name
 * themselves.
 */
function user_systemctl(string $user, string $systemctl_args, array $values = []): string
{
    if (run('command -v sudo 2>/dev/null')['output'] === '') {
        return '';
    }

    $uid = run('id -u :user 2>/dev/null', ['user' => $user])['output'];

    if ($uid === '' || !ctype_digit($uid)) {
        return '';
    }

    // env -u SUDO_ASKPASS, not just relying on sudo's own defaults: some
    // desktop sessions have SUDO_ASKPASS pointed at a GUI helper, which sudo
    // prefers over the controlling terminal whenever stdin isn't a tty (true
    // here - PHP's exec()/shell_exec() run through a pipe, not the invoking
    // shell's stdin). A graphical prompt is useless (and just hangs) over a
    // plain SSH session, so this forces sudo back onto /dev/tty, where a
    // real terminal - including an SSH one - can actually answer it.
    return run(
        'env -u SUDO_ASKPASS sudo -u :user'
        . ' XDG_RUNTIME_DIR=/run/user/' . $uid
        . ' systemctl --user ' . $systemctl_args . ' 2>&1',
        ['user' => $user] + $values
    )['output'];
}

/**
 * Stops, disables and removes a user-level unit a prior unprivileged install set
 * up for $user, so it can't keep running (holding a port or a flock) alongside
 * the system unit replacing it. `--now` on disable kills the live process, not
 * just the file. Best-effort.
 */
function remove_user_service(string $user, string $unit): void
{
    user_systemctl($user, 'disable --now :unit', ['unit' => $unit]);
    user_systemctl($user, 'daemon-reload');

    $passwd = run('getent passwd :user 2>/dev/null', ['user' => $user])['output'];
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
    $active = run('systemctl is-active :unit 2>/dev/null', ['unit' => $unit])['output'] === 'active';

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
    user_systemctl($prior_user, 'stop :unit', ['unit' => $unit]);

    if (file_put_contents($system_path, $contents) === false) {
        fail_line('Could not write ' . $system_path . ' - ' . $unit . ' left as-is.');

        if ($existing !== null) {
            @file_put_contents($system_path, $existing);
        }

        user_systemctl($prior_user, 'start :unit', ['unit' => $unit]);

        return false;
    }

    run('systemctl daemon-reload 2>&1');
    run('systemctl enable :unit 2>&1', ['unit' => $unit]);
    run('systemctl restart :unit 2>&1', ['unit' => $unit]);

    if (!system_unit_healthy($unit)) {
        fail_line('System unit ' . $unit . ' did not come up cleanly - check: systemctl status ' . $unit);

        // Restore whatever was working before rather than leave the site with a
        // dead service. A prior (good) system unit is written back and
        // restarted; otherwise fall back to the user-level service. The
        // user-level unit is only removed once the system unit is confirmed
        // healthy (below), so the fallback always still exists here.
        if ($existing !== null) {
            @file_put_contents($system_path, $existing);
            run('systemctl daemon-reload 2>&1');
            run('systemctl restart :unit 2>&1', ['unit' => $unit]);
            warn('Reverted ' . $unit . ' to its previous system unit.');
        } else {
            @unlink($system_path);
            run('systemctl daemon-reload 2>&1');
            user_systemctl($prior_user, 'start :unit', ['unit' => $unit]);
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

    $active = run('systemctl is-active :unit 2>/dev/null', ['unit' => $unit])['output'] === 'active';
    $restarts = (int) run('systemctl show -p NRestarts --value :unit 2>/dev/null', ['unit' => $unit])['output'];

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

    run('systemctl daemon-reload 2>&1');
    run('systemctl enable --now glommer-backup.timer 2>&1');

    if (run('systemctl is-enabled glommer-backup.timer 2>/dev/null')['output'] !== 'enabled') {
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
        run('systemctl start glommer-backup.service 2>&1');
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
 * On an SELinux-enforcing (or Permissive) host, plain chown/chmod never
 * applies an SELinux label - a confined httpd_t process can be denied
 * access to a file with entirely correct Unix permissions if its context
 * doesn't match what policy allows. /var/www gets httpd_sys_content_t for
 * free via a built-in path equivalence rule, which is WHY this has gone
 * unnoticed there - but a path outside it (an install in a home directory,
 * /srv, /opt, or /etc/glommer specifically, where ensure_ws_cert_readable()
 * relocates an unreadable TLS cert) defaults to a generic type httpd_t
 * isn't allowed to touch at all, and nothing here would have said so.
 * Persists the label via semanage fcontext (survives a future relabel or
 * reboot) when available, falls back to a same-boot-only chcon otherwise,
 * and is a silent no-op when SELinux isn't active (getenforce missing or
 * Disabled - nothing to fix).
 */
function ensure_selinux_context(string $path, string $type = 'httpd_sys_content_t'): void
{
    if (run('command -v getenforce 2>/dev/null')['output'] === '') {
        return;
    }

    $mode = run('getenforce 2>/dev/null')['output'];

    if ($mode !== 'Enforcing' && $mode !== 'Permissive') {
        return;
    }

    $pattern = $path . '(/.*)?';

    if (run('command -v semanage 2>/dev/null')['output'] !== ''
        && run('command -v restorecon 2>/dev/null')['output'] !== '') {
        // -a (add a new rule) first; if one already covers this exact
        // pattern from a prior run, that fails, so fall back to -m (modify
        // it) - either way $type ends up correct.
        run('semanage fcontext -a -t :type :pattern 2>/dev/null || semanage fcontext -m -t :type :pattern 2>/dev/null', ['type' => $type, 'pattern' => $pattern]);
        run('restorecon -R :path 2>&1', ['path' => $path]);

        ok('SELinux context set for ' . $path . ' (' . $type . ', persists across relabels)');

        return;
    }

    if (run('command -v chcon 2>/dev/null')['output'] !== '') {
        run('chcon -R -t :type :path 2>&1', ['type' => $type, 'path' => $path]);

        warn('SELinux is ' . $mode . ' but semanage/restorecon aren\'t available - applied a same-boot-only chcon label to ' . $path . ' instead (' . $type . '). Install policycoreutils-python-utils for a label that survives a relabel or reboot.');

        return;
    }

    warn('SELinux is ' . $mode . ' but neither semanage nor chcon is available - ' . $path . ' may be denied access by policy even though Unix permissions are correct. Install policycoreutils(-python-utils) and re-run, or label it by hand: chcon -R -t ' . $type . ' ' . $path);
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
    $project_root = dirname(__DIR__);
    $uploads = $project_root . '/uploads';
    $env_file = $project_root . '/.env';

    // /var/www gets httpd_sys_content_t for free via SELinux's built-in path
    // equivalence rule - an install anywhere else (a home directory, /srv,
    // /opt) doesn't, and Unix permissions alone won't get Apache/PHP-FPM
    // past policy on an Enforcing host. uploads/ specifically needs the
    // writable variant; the project root just needs to be readable.
    if (!str_starts_with($project_root, '/var/www')) {
        ensure_selinux_context($project_root, 'httpd_sys_content_t');
    }

    ensure_selinux_context($uploads, 'httpd_sys_rw_content_t');

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
    if (run('command -v sudo 2>/dev/null')['output'] === '') {
        return true;
    }

    // env -u SUDO_ASKPASS, same reasoning as user_systemctl() - stdin isn't a
    // tty here (exec() pipes it), so sudo would otherwise prefer a graphical
    // askpass helper if the environment has one configured, which is useless
    // (and hangs) over SSH. Forcing it off makes sudo fall back to the real
    // controlling terminal instead.
    return run('env -u SUDO_ASKPASS sudo -u :user test -r :path 2>/dev/null', ['user' => $user, 'path' => $path])['exitCode'] === 0;
}

/**
 * Re-runs ensure_ws_cert_readable() for whatever account the WS daemon's
 * system service actually runs as, resolved the same way
 * set_up_system_services() resolves it. That earlier call only ever sees
 * WS_TLS_CERT as it stood at that point in the script - a cert that gets
 * (re)generated afterward (a first-ever mkcert cert, or a refreshed one for
 * a changed SITE_URL host) needs this re-check, or .env is left pointing at
 * a path the service account can't read until a separate later run happens
 * to notice. A no-op when not root (matches the rest of the system-service
 * machinery, which is root-only) or when the account can't be determined.
 */
function relocate_ws_cert_if_root(): void
{
    if (!running_as_root()) {
        return;
    }

    $web = web_server_account();
    $service_user = $web !== null ? $web['user'] : (env_file_owner() ?? app_service_user());

    if ($service_user === null) {
        return;
    }

    $ws_user = ensure_ws_dedicated_user() ?? $service_user;

    ensure_ws_cert_readable($ws_user, $web);
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

    // When $service_user is the dedicated WS account (not literally the
    // web-server user), its own primary group scopes the cert copy to just
    // that account - tighter than sharing the web-server's group, which
    // would also cover the upload-worker and web server themselves. Falls
    // back to the web-server's group when $service_user IS that account
    // (ensure_ws_dedicated_user() failed, or $web is unknown).
    $own_group = run('id -gn :user 2>/dev/null', ['user' => $service_user])['output'];
    $is_dedicated_account = $web === null || $service_user !== $web['user'];
    $group = ($is_dedicated_account && $own_group !== '') ? $own_group : ($web['group'] ?? $service_user);
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

    // /etc/glommer gets none of /var/www's built-in SELinux path equivalence -
    // on an Enforcing host, httpd_t is denied generic etc_t content by
    // default policy regardless of how correct these Unix permissions are.
    ensure_selinux_context($dest_dir, 'httpd_sys_content_t');

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
        if (run('systemctl is-active glommer-websocket.service 2>/dev/null')['output'] === 'active') {
            run('systemctl restart glommer-websocket.service 2>&1');
        }

        ok('WebSocket cert relocated to ' . $dest_dir . ' (it was unreadable by ' . $service_user . ' in a home dir) and .env repointed.');

        // A Let's Encrypt cert renews every ~60-90 days - certbot overwrites the
        // archive/ files and repoints the live/ symlinks, but nothing re-copies
        // the fresh cert to $dest_dir afterward, so this relocation would
        // silently go stale (eventually expired) without a hook to redo it on
        // every renewal.
        if (str_starts_with($cert, '/etc/letsencrypt/live/')) {
            install_ws_cert_renewal_hook($cert, $key, $dest_cert, $dest_key, $group);
        }
    } else {
        warn('Copied the WebSocket cert to ' . $dest_dir . ' but couldn\'t update .env - set WS_TLS_CERT="' . $dest_cert . '" and WS_TLS_KEY="' . $dest_key . '" manually.');
    }
}

/**
 * Keeps the $dest_cert/$dest_key copy ensure_ws_cert_readable() just made in
 * sync with future Let's Encrypt renewals. certbot writes the renewed
 * cert/key into archive/ under new filenames and repoints the live/ symlinks
 * to them, but nothing else re-copies them to $dest_dir - without this hook,
 * the daemon would keep serving an aging copy that eventually expires. Reads
 * from $live_cert/$live_key (the live/ symlink paths, which always resolve
 * to the newest archive/ files after a renewal), so the hook itself needs no
 * per-renewal updates. Idempotent - overwrites its own hook script every
 * install run, safe to call whether or not one already exists.
 */
function install_ws_cert_renewal_hook(string $live_cert, string $live_key, string $dest_cert, string $dest_key, string $group): void
{
    $hook_dir = '/etc/letsencrypt/renewal-hooks/deploy';

    if (!is_dir($hook_dir) && !@mkdir($hook_dir, 0755, true)) {
        warn('Could not create ' . $hook_dir . ' - the WebSocket cert copy at ' . $dest_cert . ' won\'t be refreshed on renewal. Recopy it by hand after each Let\'s Encrypt renewal, or create the hook manually (see README).');

        return;
    }

    $dest_dir = escapeshellarg(dirname($dest_cert));

    $script = "#!/bin/bash\n"
        . "# Re-copies the renewed Let's Encrypt cert/key to where glommer-websocket.service\n"
        . "# (running as an unprivileged user, unlike Apache, which reads its cert before\n"
        . "# dropping privileges) can read them, then restarts it to pick up the renewal.\n"
        . "# Written by bin/install.php's install_ws_cert_renewal_hook() - re-run the\n"
        . "# installer to regenerate this file rather than editing it by hand.\n"
        . "set -euo pipefail\n\n"
        . 'cp ' . escapeshellarg($live_cert) . ' ' . escapeshellarg($dest_cert) . "\n"
        . 'cp ' . escapeshellarg($live_key) . ' ' . escapeshellarg($dest_key) . "\n"
        . 'chgrp ' . escapeshellarg($group) . ' ' . escapeshellarg($dest_cert) . ' ' . escapeshellarg($dest_key) . "\n"
        . 'chmod 0644 ' . escapeshellarg($dest_cert) . "\n"
        . 'chmod 0640 ' . escapeshellarg($dest_key) . "\n"
        // A freshly cp'd file normally inherits its parent's SELinux context
        // automatically on an Enforcing host, but this restorecon is cheap,
        // idempotent, and a no-op where SELinux isn't active - defense in
        // depth against whatever created $dest_dir not being fully labeled.
        . 'command -v restorecon >/dev/null 2>&1 && restorecon -R ' . $dest_dir . " || true\n"
        . "systemctl restart glommer-websocket.service\n";

    $hook_path = $hook_dir . '/glommer-websocket-cert.sh';

    if (@file_put_contents($hook_path, $script) === false || !@chmod($hook_path, 0755)) {
        warn('Could not write ' . $hook_path . ' - the WebSocket cert copy at ' . $dest_cert . ' won\'t be refreshed on renewal. Recopy it by hand after each Let\'s Encrypt renewal.');

        return;
    }

    ok('WebSocket cert renewal hook installed (' . $hook_path . ') - future Let\'s Encrypt renewals will keep ' . $dest_cert . ' in sync automatically.');
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

    if (run('command -v systemctl 2>/dev/null')['output'] === '') {
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

    // The WS daemon gets its own dedicated account, separate from
    // $service_user (the web-server account the upload-worker shares) - see
    // ensure_ws_dedicated_user()'s docblock for why. Falls back to
    // $service_user if account creation isn't possible on this box, same as
    // before this existed. Either way it needs .env read access (WS_SECRET,
    // WS_PORT, ...), granted read-only via group membership rather than
    // making it the file's owner.
    $ws_user = ensure_ws_dedicated_user() ?? $service_user;

    if ($ws_user !== $service_user) {
        grant_group_membership($ws_user, $web['group'] ?? $service_user);
    }

    // And make sure the WS daemon can actually reach its TLS cert -
    // relocating it out of an unreadable home dir if need be - before it's
    // (re)started below.
    ensure_ws_cert_readable($ws_user, $web);

    if (migrate_service_to_system('glommer-websocket.service', websocket_unit_contents($ws_user), $ws_user, $prior_user)) {
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
        if (run('command -v :binary 2>/dev/null', ['binary' => $binary])['output'] !== '') {
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
    $has_db_binary = run('command -v mariadbd 2>/dev/null')['output'] !== ''
        || run('command -v mysqld 2>/dev/null')['output'] !== '';
    $has_db_unit = false;

    if (run('command -v systemctl 2>/dev/null')['output'] !== '') {
        foreach (['mariadb.service', 'mysqld.service', 'mysql.service'] as $unit) {
            if (str_contains(run('systemctl list-unit-files :unit 2>/dev/null', ['unit' => $unit])['output'], $unit)) {
                $has_db_unit = true;

                break;
            }
        }
    }

    $need_mariadb_server = !$has_db_binary && !$has_db_unit;

    // mysqldump specifically (bin/backup.php calls it by that name); the
    // client package also provides mariadb-dump, so either satisfies "present".
    $need_mysqldump = run('command -v mysqldump 2>/dev/null')['output'] === ''
        && run('command -v mariadb-dump 2>/dev/null')['output'] === '';

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
            echo wrap("Install the equivalents for your distribution - a MariaDB/MySQL server, the
            mysqldump client, and the missing php-* extensions - then re-run this script.") . "\n";
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
            run('systemctl enable --now :unit 2>&1', ['unit' => $unit]);

            if (run('systemctl is-active :unit 2>/dev/null', ['unit' => $unit])['output'] === 'active') {
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
    if (run('command -v pgrep 2>/dev/null')['output'] === '') {
        return null;
    }

    if (run('pgrep -x nginx 2>/dev/null')['output'] !== '') {
        return 'nginx';
    }

    if (run('pgrep -x httpd 2>/dev/null')['output'] !== '' || run('pgrep -x apache2 2>/dev/null')['output'] !== '') {
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
    if (run('command -v certbot 2>/dev/null')['output'] !== '') {
        return true;
    }

    $install = package_install_command();

    if ($install === null) {
        return false;
    }

    echo "\nInstalling certbot (" . $install . ' certbot ' . $plugin_package . ")...\n";
    run($install . ' certbot :plugin 2>&1', ['plugin' => $plugin_package]);

    return run('command -v certbot 2>/dev/null')['output'] !== '';
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
 * Under a root install, obtains AND installs the web-server TLS certificate
 * automatically, with zero manual steps even on a plain main-server Apache with
 * no <VirtualHost> (where `certbot --apache` fails "no vhost on port 80"). It
 * installs certbot (with the plugin for whichever web server is running - Apache
 * or nginx, never assumed) and runs the WEBROOT challenge against the project
 * docroot (the app's .htaccess serves /.well-known/acme-challenge/ over http),
 * then repoints the running server's certificate directives at the fresh Let's
 * Encrypt cert. Only for a real public domain (Let's Encrypt can't
 * issue for localhost) and only once the host is reachable over HTTP (so a
 * not-ready domain doesn't burn rate limits). --keep-until-expiring makes a
 * re-run reuse an existing valid cert (so this is safe to call even when a
 * self-signed cert already "serves"). The config repoint is revert-on-failure:
 * a bad edit that fails configtest is rolled back and never reloaded, so it
 * can't strand the running site. Returns true once a certificate was obtained
 * (even if wiring it in needs manual attention - it warns), false only if
 * certbot itself failed.
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

    // Obtain with the WEBROOT challenge, not --apache/--nginx: it needs no :80
    // <VirtualHost> to exist - certbot just drops the token under the docroot and
    // the app's .htaccess serves it. certonly obtains without touching config;
    // installing into the server is done ourselves below (revert-on-failure).
    $docroot = dirname(__DIR__);

    echo "\nObtaining a Let's Encrypt certificate for " . $host . " (certbot certonly --webroot -w " . $docroot . ")...\n";

    $email_args = ($email !== '' && $email !== 'noreply@example.com' && filter_var($email, FILTER_VALIDATE_EMAIL) !== false)
        ? '-m :email'
        : '--register-unsafely-without-email';

    $certbot_result = run('certbot certonly --webroot -w :docroot --non-interactive --agree-tos --keep-until-expiring '
        . $email_args . ' -d :host 2>&1', ['docroot' => $docroot, 'host' => $host, 'email' => $email]);

    if ($certbot_result['exitCode'] !== 0) {
        warn('certbot did not complete. Fix the cause and re-run, or get a certificate manually (see README).', $certbot_result);

        return false;
    }

    $live_cert = '/etc/letsencrypt/live/' . $host . '/fullchain.pem';
    $live_key = '/etc/letsencrypt/live/' . $host . '/privkey.pem';

    if (!is_file($live_cert) || !is_file($live_key)) {
        warn('certbot reported success but ' . $live_cert . ' is not present - point ' . $server . '\'s certificate directives at the obtained cert by hand, then reload.');

        return true;
    }

    if ($server === 'nginx') {
        install_certificate_into_nginx($host, $live_cert, $live_key);
    } else {
        install_certificate_into_apache($host, $live_cert, $live_key);
    }

    return true;
}

/**
 * Repoints Apache's active SSLCertificateFile/SSLCertificateKeyFile at the given
 * (Let's Encrypt) cert/key and reloads - safely. It edits every config file
 * under /etc/httpd or /etc/apache2 that sets those directives, but ALWAYS backs
 * each up first and runs `apachectl configtest` before reloading: on any
 * configtest failure it restores every backup and reloads nothing, so a bad edit
 * can never strand the running site. Idempotent: a no-op when the directives
 * already point at $cert/$key. Warns (never throws) on anything it can't finish.
 */
function install_certificate_into_apache(string $host, string $cert, string $key): void
{
    $files = array_filter(explode("\n", run('grep -rEli "^[[:space:]]*SSLCertificate(File|KeyFile)[[:space:]]" /etc/httpd /etc/apache2 2>/dev/null')['output']));

    if ($files === []) {
        warn('Obtained a Let\'s Encrypt certificate, but found no SSLCertificateFile/SSLCertificateKeyFile directive under /etc/httpd or /etc/apache2 to repoint - set them to ' . $cert . ' and ' . $key . ' in Apache\'s config, then reload.');

        return;
    }

    // Already serving the LE cert? Nothing to do.
    $current = run('grep -rhEi "^[[:space:]]*SSLCertificate(File|KeyFile)[[:space:]]" :files 2>/dev/null', ['files' => $files])['output'];

    if (str_contains($current, $cert) && str_contains($current, $key)) {
        ok('Apache already serves the Let\'s Encrypt certificate for ' . $host);

        return;
    }

    // Back up every file BEFORE editing any - roll back the copies already made
    // and bail (touching nothing) if a backup can't be written.
    $backups = [];

    foreach ($files as $file) {
        $backup = $file . '.glommer.bak';

        if (!@copy($file, $backup)) {
            foreach ($backups as $made) {
                @unlink($made);
            }

            warn('Could not back up ' . $file . ' before editing - left Apache\'s config untouched. Set SSLCertificateFile/SSLCertificateKeyFile to ' . $cert . ' / ' . $key . ' by hand, then reload.');

            return;
        }

        $backups[$file] = $backup;
    }

    // Replace only the directive VALUE (${1} keeps the directive + leading
    // whitespace); the LE paths contain no "$"-digit sequence, so a plain
    // preg_replace is safe here.
    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);
        $contents = preg_replace('/^(\s*SSLCertificateFile\s+).*$/mi', '${1}' . $cert, $contents);
        $contents = preg_replace('/^(\s*SSLCertificateKeyFile\s+).*$/mi', '${1}' . $key, $contents);
        @file_put_contents($file, $contents);
    }

    // NEVER reload a config that doesn't pass configtest.
    $test_result = run('apachectl configtest 2>&1');

    if ($test_result['exitCode'] === 127) {
        $test_result = run('httpd -t 2>&1');
    }

    if ($test_result['exitCode'] !== 0) {
        foreach ($backups as $file => $backup) {
            @copy($backup, $file);
            @unlink($backup);
        }

        warn('Reverted the Apache config change - a Let\'s Encrypt cert was obtained (' . $cert . ') but repointing SSLCertificateFile/SSLCertificateKeyFile failed configtest. Set them by hand and reload.', $test_result);

        return;
    }

    $reload_result = run('systemctl reload httpd 2>&1');

    if ($reload_result['exitCode'] !== 0) {
        run('apachectl graceful 2>&1');
    }

    foreach ($backups as $backup) {
        @unlink($backup);
    }

    ok('Obtained and installed Let\'s Encrypt cert for ' . $host);
}

/**
 * The nginx analogue of install_certificate_into_apache(): repoints
 * ssl_certificate/ssl_certificate_key at the LE cert/key, validated with
 * `nginx -t` and revert-on-failure, then reloads. Best-effort - warns and leaves
 * the admin to finish if it can't find or safely edit the config.
 */
function install_certificate_into_nginx(string $host, string $cert, string $key): void
{
    $files = array_filter(explode("\n", run('grep -rEli "^[[:space:]]*ssl_certificate(_key)?[[:space:]]" /etc/nginx 2>/dev/null')['output']));

    if ($files === []) {
        warn('Obtained a Let\'s Encrypt certificate, but found no ssl_certificate/ssl_certificate_key directive under /etc/nginx to repoint - set them to ' . $cert . ' and ' . $key . ' by hand, then reload nginx.');

        return;
    }

    $current = run('grep -rhEi "^[[:space:]]*ssl_certificate(_key)?[[:space:]]" :files 2>/dev/null', ['files' => $files])['output'];

    if (str_contains($current, $cert) && str_contains($current, $key)) {
        ok('nginx already serves the Let\'s Encrypt certificate for ' . $host);

        return;
    }

    $backups = [];

    foreach ($files as $file) {
        $backup = $file . '.glommer.bak';

        if (!@copy($file, $backup)) {
            foreach ($backups as $made) {
                @unlink($made);
            }

            warn('Could not back up ' . $file . ' before editing - left nginx\'s config untouched. Set ssl_certificate/ssl_certificate_key to ' . $cert . ' / ' . $key . ' by hand, then reload.');

            return;
        }

        $backups[$file] = $backup;
    }

    foreach ($files as $file) {
        $contents = (string) file_get_contents($file);
        $contents = preg_replace('/^(\s*ssl_certificate_key\s+)[^;]*;/mi', '${1}' . $key . ';', $contents);
        $contents = preg_replace('/^(\s*ssl_certificate\s+)[^;]*;/mi', '${1}' . $cert . ';', $contents);
        @file_put_contents($file, $contents);
    }

    $test_result = run('nginx -t 2>&1');

    if ($test_result['exitCode'] !== 0) {
        foreach ($backups as $file => $backup) {
            @copy($backup, $file);
            @unlink($backup);
        }

        warn('Reverted the nginx config change - a Let\'s Encrypt cert was obtained (' . $cert . ') but repointing ssl_certificate/ssl_certificate_key failed nginx -t. Set them by hand and reload.', $test_result);

        return;
    }

    $reload_result = run('systemctl reload nginx 2>&1');

    if ($reload_result['exitCode'] !== 0) {
        run('nginx -s reload 2>&1');
    }

    foreach ($backups as $backup) {
        @unlink($backup);
    }

    ok('Obtained and installed Let\'s Encrypt cert for ' . $host);
}

// ---------- -1. Offer to re-exec under sudo ----------

// Before anything else - root unlocks real system services, auto-installed
// packages, and cert relocation for the steps that follow.
offer_root_reexec();

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

// Restart whatever's already running before testing it - see
// restart_already_running_daemons()'s docblock for why this isn't optional.
restart_already_running_daemons();

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

$env_just_created = !is_file(__DIR__ . '/../.env');

if ($env_just_created) {
    if (!is_interactive()) {
        fail('No .env file found in the project root. Run this script in a terminal to be walked through creating one, use the web setup wizard (visit the site in a browser), or create it by hand (copy .env.example; see src/config.php for every key and its default).');
    }

    echo wrap("No .env found - let's create one. You'll need MySQL admin credentials (an account
    with CREATE/CREATE USER/GRANT privileges, e.g. root); they're used once to provision
    the database and are never stored.") . "\n\n";

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
        echo "\n" . wrap("One-time database admin credentials are needed to create the '$db_database'
        database and its runtime account (used once, never stored). On a fresh
        MariaDB/MySQL, root has no password and authenticates only over the local socket:
        re-run this under sudo to use it automatically, or create a password admin account
        first -") . "\n\n";
        echo "  sudo mariadb -e \"CREATE USER 'glommer_admin'@'" . $db_host . "' IDENTIFIED BY 'a-strong-password'; GRANT ALL PRIVILEGES ON *.* TO 'glommer_admin'@'" . $db_host . "' WITH GRANT OPTION;\"\n\n";
        echo wrap('- then enter that account below (or root and its password, if you have set one).') . "\n\n";
    }

    while ($admin_connection === null) {
        $admin_username = prompt('Database admin username', 'root');
        $admin_password = prompt_hidden('Database admin password');

        try {
            $admin_connection = mysqli_connect($db_host, $admin_username, $admin_password, null, (int) $db_port);
        } catch (\mysqli_sql_exception $exception) {
            fail_line('Could not connect: ' . $exception -> getMessage());
            echo '       ' . wrap("On a fresh MariaDB/MySQL, root only authenticates over the local socket -
            re-run under sudo, or use a password admin account (see above). Try again.", 7) . "\n";
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
    $systemctl_usable = $is_root
        ? run('command -v systemctl 2>/dev/null')['output'] !== ''
        : user_systemd_available();

    if (!$systemctl_usable) {
        warn('Restart the WebSocket server so it loads the new WS_SECRET: ' . $ws_manual . ' (no systemctl found, or - for a non-root install - no user systemd session reachable right now; use your process manager or run that command from a real login session).');
    } else {
        $ws_restart_result = run('systemctl ' . ($is_root ? '' : '--user ') . 'restart glommer-websocket.service 2>&1');

        if ($ws_restart_result['exitCode'] === 0) {
            ok('Restarted the WebSocket server so it picks up the new WS_SECRET.');
        } else {
            warn('Could not restart the WebSocket server automatically - restart it yourself so it loads the new WS_SECRET: ' . $ws_manual, $ws_restart_result);
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

// The SMTP relay settings moved from .env to the Settings DB table (edit them
// from the admin Site Settings page now) - config.php no longer reads these
// keys at all, so check .env directly rather than $config.
$legacy_smtp_env_keys = array_filter([
    'SMTP_HOST' => Env::get('SMTP_HOST', ''),
    'SMTP_PORT' => Env::get('SMTP_PORT', ''),
    'SMTP_USERNAME' => Env::get('SMTP_USERNAME', ''),
    'SMTP_PASSWORD' => Env::get('SMTP_PASSWORD', ''),
    'SMTP_ENCRYPTION' => Env::get('SMTP_ENCRYPTION', ''),
], static fn (string $value): bool => $value !== '');

if ($legacy_smtp_env_keys !== []) {
    warn('.env still has ' . implode(', ', array_keys($legacy_smtp_env_keys)) . ' set, but the SMTP relay is no longer configured from .env - these values are ignored. Set the relay from the admin Site Settings page instead (Mail section), then remove these lines from .env.');
}

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

// Under a root install on a real public domain, obtain + install the web
// certificate automatically now that the hostname is set, then re-check. This
// runs even when a (self-signed) cert already "serves" - certbot
// --keep-until-expiring no-ops once a valid Let's Encrypt cert exists, so this
// also upgrades a self-signed cert to a trusted one. Best-effort: it only ever
// warns on failure, never hard-fails here.
if ($is_root && host_is_public_domain($site_host)) {
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
    // Auto-reuse the web server's own certificate first (root, no prompt), then
    // fall back to the interactive mkcert offer, then the manual-steps fail().
    $configured = configure_websocket_tls_from_web_server($site_host)
        || (is_interactive() && offer_websocket_tls($site_host));

    if (!$configured) {
        fail('WS_TLS_CERT/WS_TLS_KEY are not set in .env, but SITE_URL is https - browsers silently refuse a plain '
            . 'ws:// connection from an https page, so live notifications and messaging would be dead with no '
            . 'visible error. Generate a certificate for ' . $site_host . ' (mkcert is easiest for localhost/dev: '
            . 'mkcert -install && mkcert ' . $site_host . '; for a real domain you can reuse the certificate Apache '
            . 'uses) and set WS_TLS_CERT/WS_TLS_KEY in .env, then restart the WebSocket daemon. See README.md\'s '
            . 'HTTPS section.');
    }

    // set_up_system_services() already ran earlier in this same install pass
    // (and with it, its own ensure_ws_cert_readable() call) - it saw whatever
    // WS_TLS_CERT was set BEFORE this block ran, which on a first-ever mkcert
    // install is nothing yet. Without this, a freshly generated cert sits in
    // a mkcert home dir the WS service account can't read until a second,
    // separate install run happens to relocate it.
    relocate_ws_cert_if_root();

    $config = require __DIR__ . '/../src/config.php';
} elseif (is_interactive()) {
    // A WS_TLS_CERT already being set doesn't mean it's still right - SITE_URL
    // can change hostname (e.g. localhost -> a new dev domain) with nothing
    // above ever noticing the existing mkcert certificate no longer covers it.
    // Re-run mkcert for the current host every time rather than trusting a
    // cert that merely "looks" configured (offer_websocket_tls is a no-op if
    // mkcert isn't installed, and it's the same command mkcert always runs -
    // regenerating for an already-covered host is harmless). Deliberately
    // NOT re-running configure_websocket_tls_from_web_server() here too: that
    // path unconditionally restarts the WebSocket daemon, which running it on
    // every single install (even when nothing changed) would do for no
    // reason - it's only meant to fire once, the first time TLS gets wired up.
    offer_websocket_tls($site_host, refresh: true);

    // Same reasoning as the branch above: a regenerated cert lands in a
    // mkcert home dir again, which set_up_system_services() already checked
    // (and found fine, or wouldn't be here) earlier in this same pass - redo
    // that check now that the cert just changed underneath it.
    relocate_ws_cert_if_root();

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

        echo "\n" . wrap('Apply them as a MySQL admin (or re-run this script interactively / with
        DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD set) and re-run.') . "\n";
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
// A freshly-created .env already had the WebSocket server restarted for it
// above (with an explicit ok()/warn() shown at the time) - nothing left to
// tell the admin to do here for that case, and an .env that already existed
// never touched WS_SECRET in this run either. Restating "restart it" as a
// blanket next step was always stale by the time anyone reached this point.
echo '  1. ' . wrap("Visit {$config['siteURL']} and sign up - the first account created becomes
the site's administrator.", 5) . "\n";

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
