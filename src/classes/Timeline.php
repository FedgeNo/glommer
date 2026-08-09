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
     * Fans a newly created post out to everyone who should see it in their
     * friends feed: the author themselves, and every accepted friend of the
     * author at the time of posting.
     *
     * Replies too. They were held back while a reply arriving alone in a feed
     * read as an answer to a question that was not on the page; the card says
     * what it answers now. Only from here on, though - nothing goes back and
     * fans out the replies written before this, so a feed fills with them as
     * they are written rather than all at once.
     */
    public static function fanOutPost(int $author_id, int $post_id): void
    {
        self::fanOutToUsers([$author_id, ...self::friendIds($author_id)], $post_id);
    }

    /**
     * Fans a remote-origin post out to every local user who's actually
     * following that remote account (RemoteFollows, status 'accepted') -
     * never to anyone else, so a followed account's posts only ever reach
     * the specific local followers who asked for them, not a global feed.
     */
    public static function fanOutRemotePost(string $remote_actor_uri, int $post_id): void
    {
        $accepted_status = 'accepted';

        $stmt = DB::run('
SELECT `localUserId`
    FROM `RemoteFollows`
    WHERE `remoteActorURI` = ? AND `status` = ?
', 'ss', $remote_actor_uri, $accepted_status);
        $result = mysqli_stmt_get_result($stmt);

        $recipient_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $recipient_ids[] = (int) $row['localUserId'];
        }

        if ($recipient_ids !== []) {
            self::fanOutToUsers($recipient_ids, $post_id);
        }
    }

    /**
     * Puts a post into a reposter's friends' feeds, marked as theirs.
     *
     * INSERT IGNORE, so a post already in someone's feed keeps the row it has -
     * whoever it originally reached them through stays recorded, and undoing
     * the repost later leaves that alone.
     */
    public static function fanOutRepost(int $reposter_id, int $post_id): void
    {
        self::fanOutToUsers([$reposter_id, ...self::friendIds($reposter_id)], $post_id, $reposter_id);
    }

    /**
     * Takes a repost back out of the feeds it was fanned into. Only the rows
     * this repost created - a post that was already in a feed on its own
     * account keeps its place.
     */
    public static function removeRepost(int $reposter_id, int $post_id): void
    {
        DB::run('
DELETE FROM `Timelines`
    WHERE `postId` = ? AND `reposterId` = ?
', 'ii', $post_id, $reposter_id);
    }

    /**
     * @param int[] $recipient_ids
     */
    private static function fanOutToUsers(array $recipient_ids, int $post_id, ?int $reposter_id = null): void
    {
        // Every caller here fans out at the moment of the event - a post as it
        // is published, a repost as it is made - so NOW() is that moment.
        // History arriving late goes through backfillFriendship, which carries
        // each post's own time instead.
        $placeholders = implode(', ', array_fill(0, count($recipient_ids), '(?, ?, ?, NOW())'));

        $params = [];

        foreach ($recipient_ids as $recipient_id) {
            $params[] = $recipient_id;
            $params[] = $post_id;
            $params[] = $reposter_id;
        }

        DB::run('
INSERT IGNORE INTO `Timelines` (`userId`, `postId`, `reposterId`, `sortAt`)
    VALUES ' . $placeholders . '
', str_repeat('iii', count($recipient_ids)), ...$params);
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
INSERT IGNORE INTO `Timelines` (`userId`, `postId`, `sortAt`)
    SELECT ?, `postId`, `createdAt`
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
