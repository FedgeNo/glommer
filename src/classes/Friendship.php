<?php

declare(strict_types=1);

class Friendship
{
    public ?int $friendshipId = null;
    public ?int $requesterId = null;
    public ?int $addresseeId = null;
    public ?string $status = null;
    public ?string $createdAt = null;

    // Generated columns (LEAST/GREATEST of the pair, backing uniq_unordered_pair).
    // Declared so a SELECT * row's fromRow() doesn't set them as deprecated
    // dynamic properties; the app never reads them.
    public ?int $pairLow = null;
    public ?int $pairHigh = null;

    /**
     * Returns the Friendship row between two users (in either direction), or null if none exists.
     */
    public static function statusBetween(int $user_a, int $user_b): ?self
    {
        $stmt = mysqli_prepare(DB::connection(), '
SELECT *
    FROM `Friendships`
    WHERE (`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?)
');
        mysqli_stmt_bind_param($stmt, 'iiii', $user_a, $user_b, $user_b, $user_a);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = mysqli_fetch_assoc($result);

        return $row === null ? null : self::fromRow($row);
    }

    /**
     * Removes an accepted friendship between two users (either direction) and,
     * if one was actually removed, drops both their friendCount caches. The
     * single place unfriending happens - used both by the Remove Friend action
     * and by blocking (which severs a friendship as a side effect). Returns
     * whether they were in fact friends.
     */
    public static function removeAccepted(int $user_a, int $user_b): bool
    {
        $accepted_status = 'accepted';

        $stmt = mysqli_prepare(DB::connection(), '
DELETE
    FROM `Friendships`
    WHERE `status` = ? AND ((`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?))
');
        mysqli_stmt_bind_param($stmt, 'siiii', $accepted_status, $user_a, $user_b, $user_b, $user_a);
        mysqli_stmt_execute($stmt);

        if (mysqli_stmt_affected_rows($stmt) === 0) {
            return false;
        }

        User::decrementFriendCounts($user_a, $user_b);

        // Un-fan each other's posts from the other's friends feed, so an
        // ex-friend's posts stop appearing immediately (the friends feed is
        // materialized, so a read won't drop them on its own).
        Timeline::removeCrossEntries($user_a, $user_b);

        return true;
    }

    public static function fromRow(array $row): self
    {
        $friendship = new self();

        foreach ($row as $key => $value) {
            $friendship -> $key = $value;
        }

        return $friendship;
    }
}
