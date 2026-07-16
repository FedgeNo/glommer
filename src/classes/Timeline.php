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
        $recipient_ids = [$author_id, ...self::friendIds($author_id)];

        $placeholders = implode(', ', array_fill(0, count($recipient_ids), '(?, ?)'));

        $params = [];

        foreach ($recipient_ids as $recipient_id) {
            $params[] = $recipient_id;
            $params[] = $post_id;
        }

        DB::run('
INSERT IGNORE INTO `Timelines` (`userId`, `postId`)
    VALUES ' . $placeholders . '
', str_repeat('ii', count($recipient_ids)), ...$params);
    }

    /**
     * Backfills each user's existing top-level posts into the other's
     * friends feed, so a newly accepted friendship immediately shows the
     * other person's post history - matching how a live friends-list query
     * would have behaved.
     */
    public static function backfillFriendship(int $user_a, int $user_b): void
    {
        $stmt = DB::prepare('
INSERT IGNORE INTO `Timelines` (`userId`, `postId`)
    SELECT ?, `postId`
        FROM `Posts`
        WHERE `userId` = ? AND `parentId` IS NULL
');
        DB::bind($stmt, 'ii', $user_b, $user_a);
        DB::execute($stmt);

        DB::bind($stmt, 'ii', $user_a, $user_b);
        DB::execute($stmt);
    }

    /**
     * Removes each user's posts from the other's friends feed. Used when a
     * block severs an existing friendship, so the ex-friend's posts stop
     * appearing immediately rather than lingering from before the block.
     */
    public static function removeCrossEntries(int $user_a, int $user_b): void
    {
        DB::run('
DELETE `Timelines`
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    WHERE (`Timelines`.`userId` = ? AND `Posts`.`userId` = ?)
        OR (`Timelines`.`userId` = ? AND `Posts`.`userId` = ?)
', 'iiii', $user_a, $user_b, $user_b, $user_a);
    }

    /**
     * Fetches $limit + 1 rows so an extra leftover row (if present) signals more
     * history without a separate count query. Returns raw Posts rows, same
     * shape as a direct Posts query, so callers can hand them straight to
     * Post::withItemsAndCounts().
     *
     * @return array{rows: Post[], hasMore: bool}
     */
    public static function rowsForUser(int $user_id, int $limit, ?int $before_post_id = null): array
    {
        $fetch_limit = $limit + 1;
        $not_banned = 0;

        if ($before_post_id !== null) {
            $rows = DB::rows('
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ? AND `Timelines`.`postId` < ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ?
', 'Post', 'iiii', $user_id, $not_banned, $before_post_id, $fetch_limit);
        } else {
            $rows = DB::rows('
SELECT `Posts`.*
    FROM `Timelines`
    JOIN `Posts` ON `Posts`.`postId` = `Timelines`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Timelines`.`userId` = ? AND `Users`.`banned` = ?
    ORDER BY `Timelines`.`postId` DESC
    LIMIT ?
', 'Post', 'iii', $user_id, $not_banned, $fetch_limit);
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
        $accepted_status = 'accepted';

        $stmt = DB::run('
SELECT `requesterId`, `addresseeId`
    FROM `Friendships`
    WHERE `status` = ? AND (`requesterId` = ? OR `addresseeId` = ?)
', 'sii', $accepted_status, $user_id, $user_id);
        $result = mysqli_stmt_get_result($stmt);

        $friend_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $friend_ids[] = (int) $row['requesterId'] === $user_id ? (int) $row['addresseeId'] : (int) $row['requesterId'];
        }

        return $friend_ids;
    }
}
