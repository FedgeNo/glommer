<?php

declare(strict_types=1);

class Block
{
    /**
     * True if either user has blocked the other.
     */
    public static function exists(int $user_a, int $user_b): bool
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT 1
    FROM `Blocks`
    WHERE (`blockerId` = ? AND `blockedId` = ?) OR (`blockerId` = ? AND `blockedId` = ?)
');
        mysqli_stmt_bind_param($stmt, 'iiii', $user_a, $user_b, $user_b, $user_a);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * True if $blockerId has specifically blocked $blockedId (this direction only).
     */
    public static function blockedBy(int $blocker_id, int $blocked_id): bool
    {
        $stmt = mysqli_prepare(Database::connection(), '
SELECT 1
    FROM `Blocks`
    WHERE `blockerId` = ? AND `blockedId` = ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $blocker_id, $blocked_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * Blocks $blockedId on behalf of $blockerId, removing any existing friendship between them.
     */
    public static function create(int $blocker_id, int $blocked_id): void
    {
        $mysqli = Database::connection();

        $delete_friendship = mysqli_prepare($mysqli, '
DELETE
    FROM `Friendships`
    WHERE (`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?)
');
        mysqli_stmt_bind_param($delete_friendship, 'iiii', $blocker_id, $blocked_id, $blocked_id, $blocker_id);
        mysqli_stmt_execute($delete_friendship);

        Timeline::removeCrossEntries($blocker_id, $blocked_id);

        $insert = mysqli_prepare($mysqli, '
INSERT IGNORE INTO `Blocks` (`blockerId`, `blockedId`)
    VALUES (?, ?)
');
        mysqli_stmt_bind_param($insert, 'ii', $blocker_id, $blocked_id);
        mysqli_stmt_execute($insert);
    }

    /**
     * Removes a block placed by $blockerId on $blockedId.
     */
    public static function remove(int $blocker_id, int $blocked_id): void
    {
        $stmt = mysqli_prepare(Database::connection(), '
DELETE
    FROM `Blocks`
    WHERE `blockerId` = ? AND `blockedId` = ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $blocker_id, $blocked_id);
        mysqli_stmt_execute($stmt);
    }
}
