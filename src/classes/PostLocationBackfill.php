<?php

declare(strict_types=1);

/**
 * Moves post geolocation off `Posts` and into `PostLocations` (0.9.17).
 *
 * The coordinates first shipped as two nullable columns on `Posts`, which can't
 * carry a SPATIAL index (those require NOT NULL) and widened every row in the
 * table for a value almost no post has. A postId-keyed side table - the same
 * shape as FeedItems/PostHashtags/Timelines - holds only the posts that
 * actually have a location, keeps `Posts` narrow, and leaves room to add a
 * POINT column with a real spatial index later without touching `Posts`.
 *
 * Idempotent and safe to re-run: it keys off whether the old columns still
 * exist, copies with INSERT IGNORE, and only drops the columns once the rows
 * are across. A crash between the copy and the drop just means the next run
 * re-copies (ignored) and drops.
 */
class PostLocationBackfill
{
    public static function run(): void
    {
        if (!self::legacyColumnsExist()) {
            return;
        }

        // Copy first, drop second - the reverse order would lose the data if
        // the process died between the two statements.
        DB::run('
INSERT IGNORE INTO `PostLocations` (`postId`, `latitude`, `longitude`)
    SELECT `postId`, `latitude`, `longitude`
    FROM `Posts`
    WHERE `latitude` IS NOT NULL AND `longitude` IS NOT NULL
');

        $admin_connection = DB::adminConnection();

        // No DDL account available (a runtime-only deploy): the rows are safely
        // copied, so leave the now-unused columns for a later run to drop.
        if ($admin_connection === null) {
            return;
        }

        mysqli_query($admin_connection, 'ALTER TABLE `Posts` DROP COLUMN `latitude`, DROP COLUMN `longitude`');
    }

    private static function legacyColumnsExist(): bool
    {
        $column = DB::row('
SELECT `COLUMN_NAME`
    FROM `information_schema`.`COLUMNS`
    WHERE `TABLE_SCHEMA` = DATABASE() AND `TABLE_NAME` = ? AND `COLUMN_NAME` = ?
', \stdClass::class, 'ss', 'Posts', 'latitude');

        return $column !== null;
    }
}
