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
     * @return array[]
     */
    public static function rowsForAdmin(): array
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT `r`.*, `u`.`username` AS `reporterUsername`
    FROM `Reports` `r`
    JOIN `Users` `u` ON `u`.`userId` = `r`.`reporterId`
    ORDER BY `r`.`reportId` DESC
');
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $rows = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }

        return $rows;
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
