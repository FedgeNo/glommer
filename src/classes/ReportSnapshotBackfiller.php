<?php

declare(strict_types=1);

/**
 * Fills Reports.snapshot for any report created before snapshots existed,
 * from whatever content is still around (best-effort - a target already
 * deleted stays snapshotless and renders as unavailable). Not a
 * property/method of any single Report, so it doesn't belong on that class -
 * mirrors PostDeltaBackfill's own separation from Post. Race-safe and
 * idempotent via the snapshot IS NULL guard on both the select and the
 * update, run from Installer::attemptSilentUpgrade() and bin/install.php.
 */
class ReportSnapshotBackfiller
{
    public static function run(): void
    {
        $pending = DB::rows('
SELECT `reportId`, `type`, `targetId`
    FROM `Reports`
    WHERE `snapshot` IS NULL
', 'ReportData');

        foreach ($pending as $row) {
            $snapshot = Report::buildSnapshot((string) $row -> type, (int) $row -> targetId);

            if ($snapshot === null) {
                continue;
            }

            $snapshot_json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $report_id = (int) $row -> reportId;

            DB::run('
UPDATE `Reports`
    SET `snapshot` = ?
    WHERE `reportId` = ? AND `snapshot` IS NULL
', 'si', $snapshot_json, $report_id);
        }
    }
}
