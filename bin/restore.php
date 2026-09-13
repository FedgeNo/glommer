<?php

declare(strict_types=1);

// Restores the database from
// one timestamped run under the backup root.
//
//   php bin/restore.php                 # what it would do, and nothing else
//   GLOMMER_RESTORE_CONFIRMED=1 php bin/restore.php [run]
//
// A run is named, never given as a path - the name is resolved under this
// install's own backup root and nowhere else. That is what keeps one server's
// backup from landing on another: production's runs live on production, and
// there is no argument that reaches them from here.
//
// Destructive by definition. The dump drops and recreates every table it
// holds, so anything written since the backup is gone. Media is not backed
// up and this command never modifies the uploads tree. Recover configuration
// separately with the off-server private key when needed (see README).

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

// Standalone helpers have no class name for the autoloader to find them by.
require __DIR__ . '/../src/functions.php';

umask(0077);
$backup_root = Backup::rootDir();
$confirmed = (string) getenv('GLOMMER_RESTORE_CONFIRMED') === '1';

/** Database-bearing runs, oldest first; old runs do not need a media archive. */
$runs = array_values(array_filter(
    array_map('basename', glob($backup_root . '/*', GLOB_ONLYDIR) ?: []),
    static fn (string $name): bool => preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $name) === 1
        && !is_link($backup_root . '/' . $name)
        && is_file($backup_root . '/' . $name . '/database.sql.gz')
        && filesize($backup_root . '/' . $name . '/database.sql.gz') > 0
));

sort($runs);

if ($runs === []) {
    fwrite(STDERR, 'No backup runs under ' . $backup_root . " - nothing to restore from.\n");
    exit(1);
}

// A name, not a path: anything with a separator in it is refused rather than
// resolved, so no argument can reach outside this install's own backups.
$requested = $argv[1] ?? end($runs);

if (!in_array($requested, $runs, true)) {
    fwrite(STDERR, 'No such backup run: ' . $requested . "\n\nRuns available:\n  " . implode("\n  ", $runs) . "\n");
    exit(1);
}

$run_dir = $backup_root . '/' . $requested;
$dump_path = $run_dir . '/database.sql.gz';

foreach ([$dump_path] as $archive) {
    if (!is_file($archive) || filesize($archive) === 0) {
        fwrite(STDERR, 'Incomplete backup run - ' . $archive . " is missing or empty.\n");
        exit(1);
    }
}

$database = (string) Config::get('database');

echo "Restore\n";
echo '  from run:  ' . $requested . "\n";
echo '  database:  ' . $database . ' on ' . Config::get('host') . "\n";
echo '  dump:      ' . number_format((float) filesize($dump_path)) . " bytes\n";
echo "  media:     not included; existing uploads are left in place\n\n";

if (!$confirmed) {
    echo "Nothing has been changed.\n";
    echo "This replaces every table in the dump. Stop application and worker writes before restoring.\n";
    echo "Re-run with GLOMMER_RESTORE_CONFIRMED=1 to go ahead.\n";

    exit(0);
}

// ---------- Database ----------

// The app account is least-privilege and cannot drop a table, which is the
// first thing the dump does.
[$admin_user, $admin_password, $over_socket] = restore_admin_credentials();

if ($admin_user === null) {
    fwrite(STDERR, "No database account with permission to drop and create tables.\nRun as root, or set DB_ADMIN_USERNAME and DB_ADMIN_PASSWORD in .env.\n");
    exit(1);
}

// Decompressed to a file first rather than piped into the client. exec()
// reports a pipeline's LAST exit status, so a truncated or corrupt archive
// would fail in gunzip while mysql reported success - the same trap
// bin/backup.php documents on the way out.
$plain_dump = $run_dir . '/database.restore.sql';

exec(sprintf('gunzip -c %s > %s 2>&1', escapeshellarg($dump_path), escapeshellarg($plain_dump)), $gunzip_output, $gunzip_exit);

if ($gunzip_exit !== 0 || !is_file($plain_dump) || filesize($plain_dump) === 0) {
    @unlink($plain_dump);
    fwrite(STDERR, "Could not read the dump:\n" . implode("\n", $gunzip_output) . "\n");
    exit(1);
}

// Via MYSQL_PWD so it never appears in the process list - see bin/backup.php.
putenv('MYSQL_PWD=' . $admin_password);

$stderr_path = $run_dir . '/mysql.stderr';

$connection_arguments = $over_socket ? '' : sprintf(
    '--host=%s --port=%d ',
    escapeshellarg((string) Config::get('host')),
    Config::get('port')
);

exec(sprintf(
    'mysql %s--user=%s %s < %s 2>%s',
    $connection_arguments,
    escapeshellarg($admin_user),
    escapeshellarg($database),
    escapeshellarg($plain_dump),
    escapeshellarg($stderr_path)
), $load_output, $load_exit);

putenv('MYSQL_PWD');

$load_stderr = is_file($stderr_path) ? trim((string) file_get_contents($stderr_path)) : '';

@unlink($plain_dump);

if ($load_exit !== 0) {
    fwrite(STDERR, "Loading the dump failed (exit code $load_exit):\n" . $load_stderr . "\nThe database may be partially replaced; no rollback was performed.\n");
    exit(1);
}

@unlink($stderr_path);

echo "Database restored.\n";

// ---------- What came back ----------

$version = Settings::get('appVersion');
$code_version = Installer::codeVersion();

echo "\nRestored to version " . ($version ?? 'unknown') . ".\n";

// A backup taken before the code moved on is a perfectly ordinary thing to
// restore - it just leaves the database a version behind, which the site
// itself refuses to run on until the installer catches it up.
if ($version !== $code_version) {
    echo 'This code is ' . $code_version . " - run bin/install.php to bring the database up to it.\n";
}

/**
 * An account that may drop and create tables, and how to reach it.
 *
 * Root when this is run as root, the way the installer and DB::adminConnection
 * both do - and over the unix socket, which is the whole point of that
 * account: it authenticates by which user is asking, so naming a host would
 * force TCP and be refused. Everyone else uses the admin credentials .env
 * names, over the ordinary connection.
 *
 * @return array{0: ?string, 1: string, 2: bool} username, password, over the socket
 */
function restore_admin_credentials(): array
{
    if (trim((string) @shell_exec('id -u 2>/dev/null')) === '0') {
        return ['root', '', true];
    }

    $username = Env::get('DB_ADMIN_USERNAME');

    return $username === null
        ? [null, '', false]
        : [$username, (string) Env::get('DB_ADMIN_PASSWORD'), false];
}
