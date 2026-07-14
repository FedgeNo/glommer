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
     * both archives present and non-empty in some timestamped run directory.
     * A functional check (like the WebSocket reachability check), not just
     * "is BACKUP_DIR set" - proves the mechanism actually works, not merely
     * that it's configured.
     */
    public static function hasCompletedRun(): bool
    {
        $root = self::rootDir();

        if (!is_dir($root)) {
            return false;
        }

        foreach (glob($root . '/*', GLOB_ONLYDIR) ?: [] as $run_dir) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}_\d{6}$/', basename($run_dir))) {
                continue;
            }

            $database_dump = $run_dir . '/database.sql.gz';
            $uploads_archive = $run_dir . '/uploads.tar.gz';

            if (is_file($database_dump) && filesize($database_dump) > 0 && is_file($uploads_archive) && filesize($uploads_archive) > 0) {
                return true;
            }
        }

        return false;
    }
}
