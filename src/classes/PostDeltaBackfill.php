<?php

declare(strict_types=1);

/**
 * One-shot backfill of Posts.descriptionDelta for posts stored before the Delta
 * migration, run from Installer::attemptSilentUpgrade() once the column exists.
 * Those rows have old-style sanitized HTML in `description` and a NULL
 * descriptionDelta; this converts each to Delta ops (HTMLToDelta), then rewrites
 * both columns - descriptionDelta to the ops JSON, description to the derived
 * plaintext the column now holds.
 *
 * Race-safe: attemptSilentUpgrade() can run from several concurrent requests, so
 * both the select and every update are guarded by `descriptionDelta IS NULL`. A
 * row another request already converted is neither re-selected nor overwritten,
 * so a formatted delta is never clobbered by a second, formatting-less pass.
 */
class PostDeltaBackfill
{
    public static function run(): void
    {
        $connection = DB::connection();
        $empty = '';

        $select = mysqli_prepare($connection, '
SELECT `postId`, `description`
    FROM `Posts`
    WHERE `descriptionDelta` IS NULL AND `description` IS NOT NULL AND `description` <> ?
');
        mysqli_stmt_bind_param($select, 's', $empty);
        mysqli_stmt_execute($select);
        $result = mysqli_stmt_get_result($select);

        // Buffered (mysqlnd), so the whole set is in memory - safe to run the
        // per-row updates on the same connection while iterating.
        while ($row = mysqli_fetch_assoc($result)) {
            $post_id = (int) $row['postId'];
            $ops = Delta::sanitize(HTMLToDelta::convert((string) $row['description']));

            if (Delta::isBlank($ops)) {
                // Legacy markup like "<p><br></p>" is non-empty HTML that renders
                // to no text; null both columns so it matches a fresh blank post
                // (whose toDOM guard keys on descriptionDelta).
                $update = mysqli_prepare($connection, '
UPDATE `Posts`
    SET `description` = NULL, `descriptionDelta` = NULL
    WHERE `postId` = ? AND `descriptionDelta` IS NULL
');
                mysqli_stmt_bind_param($update, 'i', $post_id);
            } else {
                $plaintext = Delta::plainText($ops);
                $delta_json = json_encode(['ops' => $ops], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                $update = mysqli_prepare($connection, '
UPDATE `Posts`
    SET `description` = ?, `descriptionDelta` = ?
    WHERE `postId` = ? AND `descriptionDelta` IS NULL
');
                mysqli_stmt_bind_param($update, 'ssi', $plaintext, $delta_json, $post_id);
            }

            mysqli_stmt_execute($update);
        }
    }
}
