<?php

declare(strict_types=1);

class Friendship
{
    /**
     * A one-way link, as opposed to the mutual 'pending'/'accepted' pair.
     * Following is how the relationship with a Fediverse account is
     * expressed: the row's direction is the whole meaning, nothing ever
     * accepts it, and it never becomes a friendship. Stored here rather than
     * in its own table so one place answers "what is A to B", and so the
     * reverse direction is already available when remote accounts start
     * following ours.
     */
    public const FOLLOWING = 'following';

    public ?int $friendshipId = null;
    public ?int $requesterId = null;
    public ?int $addresseeId = null;
    public ?string $status = null;
    public ?string $createdAt = null;

    // Generated columns (LEAST/GREATEST of the pair, backing uniq_unordered_pair).
    // Declared so a SELECT * row fetched via DB::row()/DB::rows() doesn't set
    // them as deprecated dynamic properties; the app never reads them.
    public ?int $pairLow = null;
    public ?int $pairHigh = null;

    /**
     * Returns the Friendship row between two users (in either direction), or null if none exists.
     */
    public static function statusBetween(int $user_a, int $user_b): ?self
    {
        return DB::row('
SELECT *
    FROM `Friendships`
    WHERE (`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?)
', 'Friendship', 'iiii', $user_a, $user_b, $user_b, $user_a);
    }

    /**
     * Whether this viewer follows that account - the one-way link, in that
     * direction only. The reverse row is a different relationship entirely.
     */
    public static function follows(int $follower_id, int $followee_id): bool
    {
        return DB::row('
SELECT `friendshipId`
    FROM `Friendships`
    WHERE `requesterId` = ? AND `addresseeId` = ? AND `status` = ?
', 'Friendship', 'iis', $follower_id, $followee_id, self::FOLLOWING) !== null;
    }

    public static function addFollow(int $follower_id, int $followee_id): void
    {
        DB::run('
INSERT INTO `Friendships` (`requesterId`, `addresseeId`, `status`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `status` = VALUES(`status`)
', 'iis', $follower_id, $followee_id, self::FOLLOWING);
    }

    public static function removeFollow(int $follower_id, int $followee_id): void
    {
        DB::run('
DELETE
    FROM `Friendships`
    WHERE `requesterId` = ? AND `addresseeId` = ? AND `status` = ?
', 'iis', $follower_id, $followee_id, self::FOLLOWING);
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

        $stmt = DB::run('
DELETE
    FROM `Friendships`
    WHERE `status` = ? AND ((`requesterId` = ? AND `addresseeId` = ?) OR (`requesterId` = ? AND `addresseeId` = ?))
', 'siiii', $accepted_status, $user_a, $user_b, $user_b, $user_a);

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
}
