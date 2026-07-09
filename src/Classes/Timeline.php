<?php

declare(strict_types=1);

/**
 * Fan-out-on-write friends feed. Rather than computing "posts by my friends"
 * live on every read (which forces a fan-in merge across an arbitrary number
 * of friends' post streams - a shape no single index can keep sorted), each
 * top-level post is written once per interested viewer (its author, and the
 * author's friends at post time) into the `Timelines` table. A read is then
 * a single indexed range scan over one person's own rows.
 */
class Timeline
{
    /**
     * Fans a newly created top-level post out to everyone who should see it
     * in their friends feed: the author themselves, and every accepted friend
     * of the author at the time of posting.
     */
    public static function fanOutPost(int $author_id, int $post_id): void
    {
        $mysqli = Database::connection();

        $recipient_ids = [$author_id, ...self::friendIds($author_id)];

        $placeholders = implode(', ', array_fill(0, count($recipient_ids), '(?, ?)'));

        $params = [];

        foreach ($recipient_ids as $recipient_id) {
            $params[] = $recipient_id;
            $params[] = $post_id;
        }

        $stmt = mysqli_prepare($mysqli, '
INSERT IGNORE INTO `Timelines` (`userId`, `postId`)
    VALUES ' . $placeholders . '
');
        mysqli_stmt_bind_param($stmt, str_repeat('ii', count($recipient_ids)), ...$params);
        mysqli_stmt_execute($stmt);
    }

    /**
     * Backfills each user's existing top-level posts into the other's
     * friends feed, so a newly accepted friendship immediately shows the
     * other person's post history - matching how a live friends-list query
     * would have behaved.
     */
    public static function backfillFriendship(int $user_a, int $user_b): void
    {
        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
INSERT IGNORE INTO `Timelines` (`userId`, `postId`)
    SELECT ?, `postId`
        FROM `Posts`
        WHERE `userId` = ? AND `parentId` IS NULL
');
        mysqli_stmt_bind_param($stmt, 'ii', $user_b, $user_a);
        mysqli_stmt_execute($stmt);

        mysqli_stmt_bind_param($stmt, 'ii', $user_a, $user_b);
        mysqli_stmt_execute($stmt);
    }

    /**
     * Removes each user's posts from the other's friends feed. Used when a
     * block severs an existing friendship, so the ex-friend's posts stop
     * appearing immediately rather than lingering from before the block.
     */
    public static function removeCrossEntries(int $user_a, int $user_b): void
    {
        $mysqli = Database::connection();

        $stmt = mysqli_prepare($mysqli, '
DELETE `Timelines`
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    WHERE (`Timelines`.`userId` = ? AND `Posts`.`userId` = ?)
        OR (`Timelines`.`userId` = ? AND `Posts`.`userId` = ?)
');
        mysqli_stmt_bind_param($stmt, 'iiii', $user_a, $user_b, $user_b, $user_a);
        mysqli_stmt_execute($stmt);
    }

    /**
     * Fetches $limit + 1 rows so an extra leftover row (if present) signals more
     * history without a separate count query. Returns raw Posts rows, same
     * shape as a direct Posts query, so callers can hand them straight to
     * Post::fromRowsWithItems()/Thread::fromRows().
     *
     * @return array{rows: array[], hasMore: bool}
     */
    public static function rowsForUser(int $user_id, int $limit, ?int $before_post_id = null): array
    {
        $mysqli = Database::connection();
        $fetch_limit = $limit + 1;
        $not_banned = 0;

        if ($before_post_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ? AND `Timelines`.`postId` < ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iiii', $user_id, $not_banned, $before_post_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'iii', $user_id, $not_banned, $fetch_limit);
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
     * @return int[]
     */
    private static function friendIds(int $user_id): array
    {
        $mysqli = Database::connection();
        $accepted_status = 'accepted';

        $stmt = mysqli_prepare($mysqli, '
SELECT `requesterId`, `addresseeId`
    FROM `Friendships`
    WHERE `status` = ? AND (`requesterId` = ? OR `addresseeId` = ?)
');
        mysqli_stmt_bind_param($stmt, 'sii', $accepted_status, $user_id, $user_id);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $friend_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $friend_ids[] = (int) $row['requesterId'] === $user_id ? (int) $row['addresseeId'] : (int) $row['requesterId'];
        }

        return $friend_ids;
    }
}
