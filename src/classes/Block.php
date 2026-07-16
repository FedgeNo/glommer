<?php

declare(strict_types=1);

class Block
{
    /**
     * True if either user has blocked the other.
     */
    public static function exists(int $user_a, int $user_b): bool
    {
        $stmt = DB::run('
SELECT 1
    FROM `Blocks`
    WHERE (`blockerId` = ? AND `blockedId` = ?) OR (`blockerId` = ? AND `blockedId` = ?)
', 'iiii', $user_a, $user_b, $user_b, $user_a);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * True if $blockerId has specifically blocked $blockedId (this direction only).
     */
    public static function blockedBy(int $blocker_id, int $blocked_id): bool
    {
        $stmt = DB::run('
SELECT 1
    FROM `Blocks`
    WHERE `blockerId` = ? AND `blockedId` = ?
', 'ii', $blocker_id, $blocked_id);
        mysqli_stmt_store_result($stmt);

        return mysqli_stmt_num_rows($stmt) > 0;
    }

    /**
     * Blocks $blockedId on behalf of $blockerId, removing any existing friendship between them.
     */
    public static function create(int $blocker_id, int $blocked_id): void
    {
        // Reuse the one unfriend path so blocking a friend adjusts both
        // friendCounts and clears their timeline cross-entries exactly the way
        // Remove Friend does.
        Friendship::removeAccepted($blocker_id, $blocked_id);

        // Blocking also cancels any still-pending request either way (which
        // removeAccepted leaves alone - it only touches accepted friendships).
        DB::run('
DELETE
    FROM `Friendships`
    WHERE (`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?)
', 'iiii', $blocker_id, $blocked_id, $blocked_id, $blocker_id);

        DB::run('
INSERT IGNORE INTO `Blocks` (`blockerId`, `blockedId`)
    VALUES (?, ?)
', 'ii', $blocker_id, $blocked_id);
    }

    /**
     * Removes a block placed by $blockerId on $blockedId.
     */
    public static function remove(int $blocker_id, int $blocked_id): void
    {
        DB::run('
DELETE
    FROM `Blocks`
    WHERE `blockerId` = ? AND `blockedId` = ?
', 'ii', $blocker_id, $blocked_id);
    }
}
