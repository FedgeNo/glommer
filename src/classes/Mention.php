<?php

declare(strict_types=1);

/**
 * A post's @mentions: resolves @username text to real Users rows and links
 * them via PostMentions - mirrors Hashtag's indexPost()/reindexPost()/attach()
 * shape, but a mention has a side effect a hashtag doesn't (a notification to
 * the mentioned user), so that step is pulled out into notify() rather than
 * folded into indexPost()/reindexPost() themselves. This lets a transactional
 * caller (UploadBatch::finalize()) attach the PostMentions rows inside its
 * transaction and only notify() after it commits, the same "DB work now,
 * notification after commit" rule that finalize() already follows for its
 * postReady/reply notifications.
 */
class Mention
{
    // A post mentioning more than this many distinct people notifies NONE of
    // them (and is auto-reported) - the body still renders every @mention as a
    // link regardless, this only governs indexing/notifying. Kept low because
    // @mentioning someone doesn't require being friends with them: a high cap
    // would let a single post blast tag-notifications at a crowd of strangers,
    // which is the spam vector this guards against. (Hashtags cap differently -
    // they store only the first N, with no notification and no penalty.)
    public const MAX_MENTIONS = 10;

    // The auto-report's reporter: the primary admin (system) account.
    private const SYSTEM_REPORTER_ID = 1;

    /**
     * Applies the mention policy to a freshly-created post: extracts and
     * resolves @usernames to real Users rows and links them via PostMentions.
     * Returns the resolved userIds for the caller to notify() - every one of
     * them is newly-attached, since nothing existed before. Doesn't notify
     * itself, since a transactional caller may need to defer that until after
     * commit.
     *
     * @param array[] $ops the post's Delta ops
     * @return int[] userIds to notify
     */
    public static function indexPost(int $post_id, array $ops, bool $auto_report = true): array
    {
        $usernames = Delta::mentions($ops);

        if (count($usernames) > self::MAX_MENTIONS) {
            if ($auto_report) {
                Report::create(self::SYSTEM_REPORTER_ID, 'post', $post_id, 'Automatic: excessive mentions (' . count($usernames) . ')');
            }

            return [];
        }

        $user_ids = self::resolveUserIds($usernames);
        self::attach($post_id, $user_ids);

        return $user_ids;
    }

    /**
     * Like indexPost(), but for an EDITED post whose mentions may have
     * changed - including removed entirely. Diffs the newly-resolved mentions
     * against the mentions already attached to this post, so re-saving a post
     * that still mentions the same person doesn't return them again (a
     * caller's notify() would otherwise re-notify an unchanged mention on
     * every save).
     *
     * @param array[] $ops the post's edited Delta ops
     * @return int[] newly-added userIds to notify
     */
    public static function reindexPost(int $post_id, array $ops, bool $auto_report = true): array
    {
        $usernames = Delta::mentions($ops);

        if (count($usernames) > self::MAX_MENTIONS) {
            if ($auto_report) {
                Report::create(self::SYSTEM_REPORTER_ID, 'post', $post_id, 'Automatic: excessive mentions (' . count($usernames) . ')');
            }

            self::clear($post_id);

            return [];
        }

        $user_ids = self::resolveUserIds($usernames);
        $existing_user_ids = self::mentionedUserIds($post_id);

        self::clear($post_id);
        self::attach($post_id, $user_ids);

        return array_values(array_diff($user_ids, $existing_user_ids));
    }

    /**
     * The separated side effect indexPost()/reindexPost() don't perform
     * themselves: fires the 'mention' notification for each userId, skipping
     * the actor (a self-mention) and anyone who has blocked the actor - a
     * mention is a direct ping, same reasoning api/like.php and
     * api/send-message.php gate on Block::exists() for.
     *
     * @param int[] $user_ids
     */
    public static function notify(array $user_ids, int $actor_id, int $post_id): void
    {
        foreach ($user_ids as $user_id) {
            if ($user_id === $actor_id || Block::exists($actor_id, $user_id)) {
                continue;
            }

            Notification::create($user_id, $actor_id, 'mention', $post_id);
        }
    }

    /**
     * @return int[]
     */
    private static function mentionedUserIds(int $post_id): array
    {
        $stmt = DB::run('
SELECT `userId`
    FROM `PostMentions`
    WHERE `postId` = ?
', 'i', $post_id);
        $result = mysqli_stmt_get_result($stmt);

        $user_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $user_ids[] = (int) $row['userId'];
        }

        return $user_ids;
    }

    private static function clear(int $post_id): void
    {
        DB::run('
DELETE
    FROM `PostMentions`
    WHERE `postId` = ?
', 'i', $post_id);
    }

    /**
     * @param int[] $user_ids
     */
    private static function attach(int $post_id, array $user_ids): void
    {
        if ($user_ids === []) {
            return;
        }

        foreach ($user_ids as $user_id) {
            DB::run('
INSERT IGNORE INTO `PostMentions` (`postId`, `userId`)
    VALUES (?, ?)
', 'ii', $post_id, $user_id);
        }
    }

    /**
     * Resolves @usernames to real Users rows - a mention of a nonexistent
     * username simply resolves to nothing (parallel to how a syntactically
     * valid but never-posted #tag just renders a link with nothing behind it).
     *
     * @param string[] $usernames
     * @return int[]
     */
    private static function resolveUserIds(array $usernames): array
    {
        if ($usernames === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($usernames), '?'));

        $stmt = DB::run('
SELECT `userId`
    FROM `Users`
    WHERE `slug` IN (' . $placeholders . ')
', str_repeat('s', count($usernames)), ...$usernames);
        $result = mysqli_stmt_get_result($stmt);

        $user_ids = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $user_ids[] = (int) $row['userId'];
        }

        return $user_ids;
    }
}
