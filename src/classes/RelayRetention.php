<?php

declare(strict_types=1);

/**
 * Keeps the firehose from growing forever.
 *
 * A relay names every public post on every server subscribed to it, and each
 * one taken becomes a post row plus, the first time that author is seen here,
 * an account row. Nobody here asked for any of it, so it is held as a fixed
 * recent window rather than an archive: what a relay forwards is public and
 * still sitting on the server that wrote it, and a copy here that nobody has
 * read in a week is cost without a reader.
 *
 * Anything a member here touched has stopped being firehose and is never
 * swept. A reply, a quote, a like, a bookmark, a boost, a pin, a mention or an
 * open report holds a post; so does landing in somebody's timeline, which is
 * what a post that arrived through a follow *and* a relay looks like.
 * Accounts are held by the same kind of tie - anyone following them, anyone
 * they follow, a message, a friendship, a block.
 *
 * Nothing swept is tombstoned. A tombstone says "this was deleted at the
 * source"; these are only old. The same post named again tomorrow, or the same
 * author posting again next week, has to be able to come straight back in.
 */
class RelayRetention
{
    /**
     * How many relayed posts this server keeps. Everything older than the
     * newest this many goes, provided nothing here holds it.
     *
     * Comfortably more than the window trending reads (EntityRanker::WINDOW_SIZE),
     * so that window is a choice about how much to score rather than a
     * description of everything there was. Kept the same distance clear as it
     * grows: a store the size of the window means the oldest post trending
     * looks at is also the oldest post there is.
     */
    public const RETAINED_POSTS = 20000;

    /**
     * How much one pass may take. A sweep competes with live traffic, so it
     * takes a bite and comes back rather than holding rows for a minute.
     */
    public const BATCH_SIZE = 500;

    /** Prunes the aged-out posts, then the accounts left holding nothing. */
    public static function sweep(int $retained = self::RETAINED_POSTS): void
    {
        self::prunePosts($retained);
        self::pruneAccounts();
    }

    /** @return int how many posts went */
    public static function prunePosts(int $retained = self::RETAINED_POSTS): int
    {
        $cutoff = self::oldestKeptPostId($retained);

        if ($cutoff === null) {
            return 0;
        }

        $doomed = self::prunablePostIds($cutoff);

        if ($doomed === []) {
            return 0;
        }

        // Straight at the row rather than through Post::delete(), which
        // tombstones a remote post - right when somebody deletes one on
        // purpose, wrong for one that merely aged out. FeedItems, the
        // RelayPosts row and the rest cascade; a relayed post keeps no media
        // of its own here (FeedItems.remoteURL - the bytes stay on the origin
        // server), so there is nothing on disk to go with it.
        $placeholders = implode(', ', array_fill(0, count($doomed), '?'));

        DB::run('
DELETE
    FROM `Posts`
    WHERE `postId` IN (' . $placeholders . ')
', str_repeat('i', count($doomed)), ...$doomed);

        return count($doomed);
    }

    /** @return int how many accounts went */
    public static function pruneAccounts(): int
    {
        $doomed = self::prunableAccountIds();

        foreach ($doomed as $user_id) {
            // The one path that takes an account apart properly (avatar file,
            // the token tables carrying no FK). It also already declines to
            // retire a remote handle, which this depends on: retiring one
            // would lock the author out of ever being stored here again.
            User::delete($user_id);
        }

        return count($doomed);
    }

    /**
     * The postId of the oldest relayed post inside the window, or null while
     * there are fewer than a windowful and nothing is old enough to go.
     */
    private static function oldestKeptPostId(int $retained): ?int
    {
        $skip = max(0, $retained - 1);

        $result = mysqli_stmt_get_result(DB::run('
SELECT `postId`
    FROM `RelayPosts`
    ORDER BY `postId` DESC
    LIMIT 1 OFFSET ?
', 'i', $skip));

        $row = mysqli_fetch_assoc($result);

        return $row === null ? null : (int) $row['postId'];
    }

    /**
     * Relayed posts older than the window that nothing here holds.
     *
     * @return int[]
     */
    private static function prunablePostIds(int $cutoff): array
    {
        $post_report = 'post';
        $limit = self::BATCH_SIZE;

        $result = mysqli_stmt_get_result(DB::run('
SELECT `RelayPosts`.`postId`
    FROM `RelayPosts`
    WHERE `RelayPosts`.`postId` < ?
        AND NOT EXISTS (SELECT 1 FROM `Posts` `reply` WHERE `reply`.`parentId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `Posts` `quote` WHERE `quote`.`quotedPostId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `Timelines` WHERE `Timelines`.`postId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `Announces` WHERE `Announces`.`postId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `PinnedPosts` WHERE `PinnedPosts`.`postId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `PostMentions` WHERE `PostMentions`.`postId` = `RelayPosts`.`postId`)
        AND NOT EXISTS (SELECT 1 FROM `Reports` WHERE `Reports`.`type` = ? AND `Reports`.`targetId` = `RelayPosts`.`postId`)
    ORDER BY `RelayPosts`.`postId`
    LIMIT ?
', 'isi', $cutoff, $post_report, $limit));

        $post_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $post_ids[] = (int) $row['postId'];
        }

        return $post_ids;
    }

    /**
     * Accounts from elsewhere that nothing here is attached to any more -
     * every post they were stored for has aged out and nobody ever followed,
     * messaged or blocked them.
     *
     * Local members are not candidates at any point: the remoteActorURI test
     * is what separates a shadow row this server invented to hang a relayed
     * post on from a person who signed up here. A banned one stays too, since
     * the row is where the ban lives.
     *
     * @return int[]
     */
    private static function prunableAccountIds(): array
    {
        $not_banned = 0;
        $limit = self::BATCH_SIZE;

        $result = mysqli_stmt_get_result(DB::run('
SELECT `Users`.`userId`
    FROM `Users`
    WHERE `Users`.`remoteActorURI` IS NOT NULL
        AND `Users`.`banned` = ?
        AND NOT EXISTS (SELECT 1 FROM `Posts` WHERE `Posts`.`userId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `Likes` WHERE `Likes`.`userId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `Announces` WHERE `Announces`.`userId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`userId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `PinnedPosts` WHERE `PinnedPosts`.`userId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `PostMentions` WHERE `PostMentions`.`userId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `Messages` WHERE `Messages`.`senderId` = `Users`.`userId` OR `Messages`.`recipientId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `Friendships` WHERE `Friendships`.`requesterId` = `Users`.`userId` OR `Friendships`.`addresseeId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `Blocks` WHERE `Blocks`.`blockerId` = `Users`.`userId` OR `Blocks`.`blockedId` = `Users`.`userId`)
        AND NOT EXISTS (SELECT 1 FROM `RemoteFollows` WHERE `RemoteFollows`.`remoteActorURI` = `Users`.`remoteActorURI`)
        AND NOT EXISTS (SELECT 1 FROM `FediverseFollowers` WHERE `FediverseFollowers`.`remoteActorURI` = `Users`.`remoteActorURI`)
        AND NOT EXISTS (SELECT 1 FROM `Relays` WHERE `Relays`.`actorURI` = `Users`.`remoteActorURI`)
    ORDER BY `Users`.`userId`
    LIMIT ?
', 'ii', $not_banned, $limit));

        $user_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $user_ids[] = (int) $row['userId'];
        }

        return $user_ids;
    }
}
