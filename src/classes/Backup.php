<?php

declare(strict_types=1);

/**
 * Shared backup-location logic between bin/backup.php (which performs the
 * backup) and EnvironmentChecker (which verifies one has actually completed) -
 * one source of truth for where backups live, so the check can never disagree
 * with reality about where to look.
 */
class Backup
{
    public static function rootDir(): string
    {
        $project_root = dirname(__DIR__, 2);

        return Env::get('BACKUP_DIR', '') ?: dirname($project_root) . '/glommer-backups';
    }

    /**
     * Whether at least one backup run has actually completed successfully -
     * database and encrypted recovery bundle present and non-empty in some timestamped run directory.
     * A functional check (like the WebSocket reachability check), not just
     * "is BACKUP_DIR set" - proves the mechanism actually works, not merely
     * that it's configured.
     */
    public static function hasCompletedRun(): bool
    {
        return self::archiveStatus()['time'] !== null;
    }

    /** Metadata only: never read the database dump or scan the uploads tree. */
    public static function archiveStatus(?string $root = null): array
    {
        $root ??= self::rootDir();

        if (!is_dir($root) || !is_readable($root)) {
            return ['readable' => false, 'time' => null];
        }

        $latest = null;

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $directory) {
            if (is_link($directory) || !preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', basename($directory))) {
                continue;
            }

            $dump = $directory . '/database.sql.gz';
            $recovery = $directory . '/' . BackupRecovery::FILENAME;

            if (is_file($dump) && is_file($recovery) && @filesize($dump) > 0 && @filesize($recovery) > 0) {
                $modified = max((int) @filemtime($dump), (int) @filemtime($recovery));
                $latest = max($latest ?? 0, $modified);
            }
        }

        return ['readable' => true, 'time' => $latest];
    }
}
