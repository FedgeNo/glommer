<?php

declare(strict_types=1);

class Report
{
    /**
     * Files a report. Returns false if this reporter already has a report on
     * this exact target (the reporter_target unique key rejects the duplicate)
     * so the caller can report that back rather than 500.
     */
    public static function create(int $reporter_id, string $target_type, int $target_id, ?string $reason): bool
    {
        // Snapshot the reported content at report time so the moderator judges
        // what was actually reported, not whatever it's since been edited to (or
        // deleted). See buildSnapshot.
        $snapshot = self::buildSnapshot($target_type, $target_id);
        $snapshot_json = $snapshot !== null ? json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null;

        try {
            DB::run('
INSERT INTO `Reports` (`reporterId`, `targetType`, `targetId`, `reason`, `snapshot`)
    VALUES (?, ?, ?, ?, ?)
', 'isiss', $reporter_id, $target_type, $target_id, $reason, $snapshot_json);
        } catch (\mysqli_sql_exception $exception) {
            // 1062 = the reporter_target unique key rejected a duplicate report
            // from the same reporter for the same target. Anything else is a
            // real failure.
            if ($exception -> getCode() === 1062) {
                return false;
            }

            throw $exception;
        }

        return true;
    }

    /**
     * Whether this content has already had a report dismissed by a moderator,
     * in which case it can't be reported again. Only posts and messages carry
     * the flag (a user isn't a single piece of reviewable content); anything
     * else is never "dismissed" this way.
     */
    public static function isContentDismissed(string $target_type, int $target_id): bool
    {
        $table = match ($target_type) {
            'post' => 'Posts',
            'message' => 'Messages',
            default => null,
        };

        if ($table === null) {
            return false;
        }

        $id_column = $target_type === 'post' ? 'postId' : 'messageId';
        $class = $target_type === 'post' ? 'Post' : 'Message';

        $content = DB::row('
SELECT `reportsDismissed`
    FROM `' . $table . '`
    WHERE `' . $id_column . '` = ?
', $class, 'i', $target_id);

        return $content !== null && $content -> reportsDismissed === 1;
    }

    /**
     * Marks a post/message as reviewed-and-dismissed so it can't be reported
     * again. A no-op for a user target (no flag column).
     */
    public static function markContentDismissed(string $target_type, int $target_id): void
    {
        $table = match ($target_type) {
            'post' => 'Posts',
            'message' => 'Messages',
            default => null,
        };

        if ($table === null) {
            return;
        }

        $id_column = $target_type === 'post' ? 'postId' : 'messageId';
        $dismissed = 1;

        DB::run('
UPDATE `' . $table . '`
    SET `reportsDismissed` = ?
    WHERE `' . $id_column . '` = ?
', 'ii', $dismissed, $target_id);
    }

    /**
     * The forensic snapshot of a report's target at report time. A post or
     * message is captured as its whole row plus, for a post, an array of its
     * attachment (FeedItem) ids - enough to recover the originals later from
     * uploads/private/originals, which are kept rather than deleted for exactly
     * this. A user is captured from an explicit allowlist, never the whole row
     * (that carries passwordHash and email). Null when the target's already gone.
     *
     * @return array<string, mixed>|null
     */
    private static function buildSnapshot(string $target_type, int $target_id): ?array
    {
        if ($target_type === 'post') {
            $row = self::snapshotRow('Posts', 'postId', $target_id);

            if ($row === null) {
                return null;
            }

            $row['attachmentIds'] = self::attachmentIds($target_id);

            return $row;
        }

        if ($target_type === 'message') {
            return self::snapshotRow('Messages', 'messageId', $target_id);
        }

        if ($target_type === 'user') {
            $user = User::load($target_id);

            if ($user === null) {
                return null;
            }

            return [
                'userId' => (int) $user -> userId,
                'username' => $user -> username,
                'displayName' => $user -> displayName,
                'hasAvatar' => (int) $user -> hasAvatar,
                'createdAt' => $user -> createdAt,
            ];
        }

        return null;
    }

    /**
     * The whole row of $table by primary key. Fetched via a native-typed result
     * (mysqlnd) so ints stay ints through json_encode/json_decode and rebuild
     * cleanly into typed model properties.
     *
     * @return array<string, mixed>|null
     */
    private static function snapshotRow(string $table, string $id_column, int $id): ?array
    {
        $stmt = DB::run('
SELECT *
    FROM `' . $table . '`
    WHERE `' . $id_column . '` = ?
', 'i', $id);

        return mysqli_fetch_assoc(mysqli_stmt_get_result($stmt)) ?: null;
    }

    /**
     * @return int[] the FeedItem ids attached to a post, in id order
     */
    private static function attachmentIds(int $post_id): array
    {
        $stmt = DB::run('
SELECT `itemId`
    FROM `FeedItems`
    WHERE `postId` = ?
    ORDER BY `itemId`
', 'i', $post_id);
        $result = mysqli_stmt_get_result($stmt);

        $ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $ids[] = (int) $row['itemId'];
        }

        return $ids;
    }

    /**
     * Fills the snapshot for any report created before snapshots existed, from
     * whatever content is still around (best-effort - a target already deleted
     * stays snapshotless and renders as unavailable). Race-safe and idempotent
     * via the snapshot IS NULL guard on both the select and the update.
     */
    public static function backfillSnapshots(): void
    {
        $pending = DB::rows('
SELECT `reportId`, `targetType`, `targetId`
    FROM `Reports`
    WHERE `snapshot` IS NULL
', 'ReportData');

        foreach ($pending as $row) {
            $snapshot = self::buildSnapshot((string) $row -> targetType, (int) $row -> targetId);

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

    /**
     * Whether the live post or message a report targets still exists - a
     * deleted one still shows (from its snapshot) but has nothing left to delete,
     * so its card drops the Delete button. Only posts and messages are deletable.
     */
    public static function contentExists(string $target_type, int $target_id): bool
    {
        $table = match ($target_type) {
            'post' => 'Posts',
            'message' => 'Messages',
            default => null,
        };

        if ($table === null) {
            return false;
        }

        $id_column = $target_type === 'post' ? 'postId' : 'messageId';

        $stmt = DB::run('
SELECT 1
    FROM `' . $table . '`
    WHERE `' . $id_column . '` = ?
', 'i', $target_id);

        return mysqli_fetch_row(mysqli_stmt_get_result($stmt)) !== null;
    }

    /**
     * The moderation queue, newest first. Cursor-paginate by passing the
     * reportId of the last report already seen as $before_report_id; omit it
     * for the first page. Returns $limit rows plus a hasMore flag (fetches one
     * extra to detect a next page without a second count query), the same shape
     * as Post::globalFeedRows. The reportId cursor
     * stays correct even as reports are dismissed out of the queue underneath
     * the moderator - a page may just return fewer rows.
     *
     * @return array{rows: ReportData[], hasMore: bool}
     */
    public static function rowsForAdmin(int $limit, ?int $before_report_id = null): array
    {
        $fetch_limit = $limit + 1;

        if ($before_report_id !== null) {
            $rows = DB::rows('
SELECT `r`.*, `u`.`username` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    WHERE `r`.`reportId` < ?
    ORDER BY `r`.`reportId` DESC
    LIMIT ?
', 'ReportData', 'ii', $before_report_id, $fetch_limit);
        } else {
            $rows = DB::rows('
SELECT `r`.*, `u`.`username` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    ORDER BY `r`.`reportId` DESC
    LIMIT ?
', 'ReportData', 'i', $fetch_limit);
        }

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
        }

        return ['rows' => $rows, 'hasMore' => $has_more];
    }

    public static function find(int $report_id): ?ReportData
    {
        return DB::row('
SELECT `targetType`, `targetId`
    FROM `Reports`
    WHERE `reportId` = ?
', 'ReportData', 'i', $report_id);
    }

    public static function delete(int $report_id): void
    {
        DB::run('
DELETE
    FROM `Reports`
    WHERE `reportId` = ?
', 'i', $report_id);
    }

    /**
     * The userId a report target resolves to, or null if $target_type is
     * unrecognized or $target_id doesn't actually exist - api/report.php
     * relies on that null to reject reports filed against nonexistent ids.
     */
    public static function resolveTargetUserId(string $target_type, int $target_id): ?int
    {
        return match ($target_type) {
            'user' => User::load($target_id) !== null ? $target_id : null,
            'post' => self::postAuthorId($target_id),
            'message' => self::messageAuthorId($target_id),
            default => null,
        };
    }

    protected static function postAuthorId(int $post_id): ?int
    {
        $post = DB::row('
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);

        return $post !== null ? (int) $post -> userId : null;
    }

    protected static function messageAuthorId(int $message_id): ?int
    {
        $message = DB::row('
SELECT `senderId`
    FROM `Messages`
    WHERE `messageId` = ?
', 'Message', 'i', $message_id);

        return $message !== null ? (int) $message -> senderId : null;
    }
}
