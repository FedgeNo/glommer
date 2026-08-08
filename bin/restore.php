<?php

declare(strict_types=1);

// Puts back what bin/backup.php took: the database and the uploads tree, from
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
// holds, so anything written since the backup is gone; the uploads tree is
// moved aside rather than deleted, because a mistaken restore should be
// survivable and 300MB of somebody's pictures is not worth being brave with.

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

$project_root = dirname(__DIR__);
$backup_root = Backup::rootDir();
$confirmed = (string) getenv('GLOMMER_RESTORE_CONFIRMED') === '1';

/** Every completed run, oldest first. */
$runs = array_values(array_filter(
    array_map('basename', glob($backup_root . '/*', GLOB_ONLYDIR) ?: []),
    static fn (string $name): bool => preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', $name) === 1
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
$uploads_path = $run_dir . '/uploads.tar.gz';

foreach ([$dump_path, $uploads_path] as $archive) {
    if (!is_file($archive) || filesize($archive) === 0) {
        fwrite(STDERR, 'Incomplete backup run - ' . $archive . " is missing or empty.\n");
        exit(1);
    }
}

$database = (string) Config::get('database');

echo "Restore\n";
echo '  from run:  ' . $requested . "\n";
echo '  database:  ' . $database . ' on ' . Config::get('host') . "\n";
echo '  uploads:   ' . $project_root . "/uploads\n";
echo '  dump:      ' . number_format((float) filesize($dump_path)) . " bytes\n";
echo '  archive:   ' . number_format((float) filesize($uploads_path)) . " bytes\n\n";

if (!$confirmed) {
    echo "Nothing has been changed.\n";
    echo "This replaces every table in the dump and moves the uploads tree aside.\n";
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
    fwrite(STDERR, "Loading the dump failed (exit code $load_exit):\n" . $load_stderr . "\n");
    exit(1);
}

@unlink($stderr_path);

echo "Database restored.\n";

// ---------- Uploads ----------

$uploads_dir = $project_root . '/uploads';
$moved_aside = null;

if (is_dir($uploads_dir)) {
    // Moved rather than deleted: a restore onto the wrong install should be
    // survivable, and this is the only copy of anything not in the archive.
    $moved_aside = $uploads_dir . '.before-restore-' . date('Y-m-d_His');

    if (!rename($uploads_dir, $moved_aside)) {
        fwrite(STDERR, 'Could not move the existing uploads tree aside.' . "\n");
        exit(1);
    }
}

exec(sprintf(
    'tar -xzf %s -C %s 2>&1',
    escapeshellarg($uploads_path),
    escapeshellarg($project_root)
), $tar_output, $tar_exit);

if ($tar_exit !== 0) {
    fwrite(STDERR, "Extracting the uploads archive failed:\n" . implode("\n", $tar_output) . "\n");

    if ($moved_aside !== null) {
        rename($moved_aside, $uploads_dir);
        fwrite(STDERR, "The previous uploads tree has been put back.\n");
    }

    exit(1);
}

// The web server writes here, so it has to own it however the archive was
// made or whoever ran this.
$web_user = trim((string) shell_exec('id -u apache >/dev/null 2>&1 && echo apache || echo www-data'));
exec(sprintf('chown -R %s:%s %s 2>&1', escapeshellarg($web_user), escapeshellarg($web_user), escapeshellarg($uploads_dir)));

echo "Uploads restored.\n";

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

if ($moved_aside !== null) {
    echo 'The uploads tree that was here is at ' . $moved_aside . " - remove it once you are satisfied.\n";
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
