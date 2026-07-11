<?php

declare(strict_types=1);

// Backs up everything a restore needs and git doesn't hold: the database
// (mysqldump, gzipped) and the uploads tree (tar.gz, originals included).
// Each run writes a timestamped directory under the backup root and prunes
// runs older than the retention window. Intended to run nightly from a
// systemd user timer - see README's "Backups" section.
//
//   BACKUP_DIR       backup root (default: <parent of project>/glommer-backups)
//   BACKUP_KEEP_DAYS retention in days (default: 7)

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(function (string $class): void {
    $file = __DIR__ . '/../src/Classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});

$config = require __DIR__ . '/../src/config.php';

$project_root = dirname(__DIR__);
$backup_root = Env::get('BACKUP_DIR', '') ?: dirname($project_root) . '/glommer-backups';
$keep_days = max(1, (int) (Env::get('BACKUP_KEEP_DAYS', '') ?: 7));

if (!is_dir($backup_root) && !@mkdir($backup_root, 0700, true)) {
    fwrite(STDERR, 'Could not create backup directory ' . $backup_root . "\n");
    exit(1);
}

// Refuse a backup root inside the project - backups must not be web-reachable
// (a database dump served over HTTP would be a full data breach), and backing
// the backups into themselves compounds forever.
if (str_starts_with((string) realpath($backup_root), (string) realpath($project_root))) {
    fwrite(STDERR, 'BACKUP_DIR must be outside the project root (' . $project_root . ") - backups must never be web-servable.\n");
    exit(1);
}

$run_dir = $backup_root . '/' . date('Y-m-d_His');

if (!mkdir($run_dir, 0700)) {
    fwrite(STDERR, 'Could not create ' . $run_dir . "\n");
    exit(1);
}

// ---------- Database ----------

// The password travels via MYSQL_PWD so it never appears in the process list.
// --single-transaction gives a consistent InnoDB snapshot without needing the
// LOCK TABLES privilege the least-privilege runtime account doesn't have.
$dump_path = $run_dir . '/database.sql.gz';
$dump_command = sprintf(
    'MYSQL_PWD=%s mysqldump --single-transaction --skip-lock-tables --host=%s --port=%d --user=%s %s 2>&1 | gzip > %s',
    escapeshellarg($config['password']),
    escapeshellarg($config['host']),
    $config['port'],
    escapeshellarg($config['username']),
    escapeshellarg($config['database']),
    escapeshellarg($dump_path)
);

exec($dump_command, $dump_output, $dump_exit);

// A failed mysqldump still produces a (tiny, error-text) .gz through the pipe,
// so check the exit code AND that the dump gunzips to something real.
$dump_ok = $dump_exit === 0 && filesize($dump_path) > 512;

if (!$dump_ok) {
    fwrite(STDERR, "Database dump failed:\n" . implode("\n", $dump_output) . "\n");
    fwrite(STDERR, 'Partial output left in ' . $run_dir . " for inspection.\n");
    exit(1);
}

echo 'database.sql.gz: ' . number_format((float) filesize($dump_path)) . " bytes\n";

// ---------- Uploads ----------

$uploads_path = $run_dir . '/uploads.tar.gz';

exec(sprintf(
    'tar -czf %s -C %s uploads 2>&1',
    escapeshellarg($uploads_path),
    escapeshellarg($project_root)
), $tar_output, $tar_exit);

if ($tar_exit !== 0) {
    fwrite(STDERR, "Uploads archive failed:\n" . implode("\n", $tar_output) . "\n");
    exit(1);
}

echo 'uploads.tar.gz: ' . number_format((float) filesize($uploads_path)) . " bytes\n";

// ---------- Retention ----------

$cutoff = time() - $keep_days * 86400;
$pruned = 0;

foreach (glob($backup_root . '/*', GLOB_ONLYDIR) as $old_dir) {
    // Only touch directories matching our own timestamp naming - never
    // delete something else that happens to live in the backup root.
    if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', basename($old_dir))) {
        continue;
    }

    $run_time = \DateTime::createFromFormat('Y-m-d_His', basename($old_dir));

    if ($run_time !== false && $run_time -> getTimestamp() < $cutoff) {
        foreach (glob($old_dir . '/*') as $old_file) {
            unlink($old_file);
        }

        rmdir($old_dir);
        $pruned++;
    }
}

echo 'Backup complete: ' . $run_dir . ($pruned > 0 ? ' (' . $pruned . ' expired run(s) pruned)' : '') . "\n";
