<?php

declare(strict_types=1);

class Report
{
    public static function create(int $reporter_id, string $target_type, int $target_id, ?string $reason): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
INSERT INTO `Reports` (`reporterId`, `targetType`, `targetId`, `reason`)
    VALUES (?, ?, ?, ?)
');
        mysqli_stmt_bind_param($stmt, 'isis', $reporter_id, $target_type, $target_id, $reason);
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

    public static function resolveTargetUserId(string $target_type, int $target_id): ?int
    {
        return match ($target_type) {
            'user' => $target_id,
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
