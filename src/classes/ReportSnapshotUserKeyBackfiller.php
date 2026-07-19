<?php

declare(strict_types=1);

/**
 * Rewrites user-target Reports.snapshot rows still carrying the pre-rename
 * username/displayName keys to the row-named slug/title that User::fromRow
 * reads, so a report of a since-deleted account created before the rename
 * still renders its identity. Not a property/method of any single Report,
 * so it doesn't belong on that class - mirrors PostDeltaBackfill's own
 * separation from Post. Idempotent - a snapshot already on the new keys has
 * neither old key and is skipped. Run from Installer::attemptSilentUpgrade()
 * only; bin/install.php doesn't apply this one.
 */
class ReportSnapshotUserKeyBackfiller
{
    public static function run(): void
    {
        $target_user = 'user';

        $rows = DB::rows('
SELECT `reportId`, `snapshot`
    FROM `Reports`
    WHERE `type` = ? AND `snapshot` IS NOT NULL
', 'ReportData', 's', $target_user);

        foreach ($rows as $row) {
            $snapshot = json_decode((string) $row -> snapshot, true);

            if (!is_array($snapshot) || (!array_key_exists('username', $snapshot) && !array_key_exists('displayName', $snapshot))) {
                continue;
            }

            if (array_key_exists('username', $snapshot)) {
                $snapshot['slug'] = $snapshot['username'];
                unset($snapshot['username']);
            }

            if (array_key_exists('displayName', $snapshot)) {
                $snapshot['title'] = $snapshot['displayName'];
                unset($snapshot['displayName']);
            }

            $snapshot_json = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $report_id = (int) $row -> reportId;

            DB::run('
UPDATE `Reports`
    SET `snapshot` = ?
    WHERE `reportId` = ?
', 'si', $snapshot_json, $report_id);
        }
    }
}
