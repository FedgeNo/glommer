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
        $stmt = mysqli_prepare(Database::connection(), '
INSERT INTO `Reports` (`reporterId`, `targetType`, `targetId`, `reason`)
    VALUES (?, ?, ?, ?)
');
        mysqli_stmt_bind_param($stmt, 'isis', $reporter_id, $target_type, $target_id, $reason);

        try {
            mysqli_stmt_execute($stmt);
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

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `reportsDismissed`
    FROM `' . $table . '`
    WHERE `' . $id_column . '` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $target_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        return $row !== null && (int) $row['reportsDismissed'] === 1;
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

        $stmt = mysqli_prepare(Database::connection(), '
UPDATE `' . $table . '`
    SET `reportsDismissed` = ?
    WHERE `' . $id_column . '` = ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $dismissed, $target_id);
        mysqli_stmt_execute($stmt);
    }

    /**
     * Deletes reports whose reported post or message no longer exists - a post
     * or message a user deleted on their own leaves its report behind (the
     * Reports target is polymorphic, so there's no FK to cascade it). Run before
     * showing the moderation queue so a deleted target's report never appears
     * (there'd be nothing to view or act on). User targets are left alone.
     */
    public static function purgeOrphaned(): void
    {
        $post = 'post';
        $message = 'message';

        $stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `Reports`
    WHERE (`targetType` = ? AND `targetId` NOT IN (SELECT `postId` FROM `Posts`))
        OR (`targetType` = ? AND `targetId` NOT IN (SELECT `messageId` FROM `Messages`))
');
        mysqli_stmt_bind_param($stmt, 'ss', $post, $message);
        mysqli_stmt_execute($stmt);
    }

    /**
     * The moderation queue, newest first. Cursor-paginate by passing the
     * reportId of the last report already seen as $before_report_id; omit it
     * for the first page. Returns $limit rows plus a hasMore flag (fetches one
     * extra to detect a next page without a second count query), the same shape
     * as Post::globalFeedRows / Notification::rowsForUser. The reportId cursor
     * stays correct even as reports are dismissed out of the queue underneath
     * the moderator - a page may just return fewer rows.
     *
     * @return array{rows: array[], hasMore: bool}
     */
    public static function rowsForAdmin(int $limit, ?int $before_report_id = null): array
    {
        $mysqli = Database::connection();
        $fetch_limit = $limit + 1;

        if ($before_report_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `r`.*, `u`.`username` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    WHERE `r`.`reportId` < ?
    ORDER BY `r`.`reportId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'ii', $before_report_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `r`.*, `u`.`username` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    ORDER BY `r`.`reportId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'i', $fetch_limit);
        }

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        $has_more = count($rows) > $limit;

        if ($has_more) {
            array_pop($rows);
        }

        return ['rows' => $rows, 'hasMore' => $has_more];
    }

    /**
     * @return array{targetType: string, targetId: int}|null
     */
    public static function find(int $report_id): ?array
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `targetType`, `targetId`
    FROM `Reports`
    WHERE `reportId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $report_id);
        mysqli_stmt_execute($stmt);
        $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

        if ($row === null) {
            return null;
        }

        return ['targetType' => (string) $row['targetType'], 'targetId' => (int) $row['targetId']];
    }

    public static function delete(int $report_id): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `Reports`
    WHERE `reportId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $report_id);
        mysqli_stmt_execute($stmt);
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
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `userId`
    FROM `Posts`
    WHERE `postId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $post_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row !== null ? (int) $row['userId'] : null;
    }

    protected static function messageAuthorId(int $message_id): ?int
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `senderId`
    FROM `Messages`
    WHERE `messageId` = ?
');
        mysqli_stmt_bind_param($stmt, 'i', $message_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row !== null ? (int) $row['senderId'] : null;
    }
}
