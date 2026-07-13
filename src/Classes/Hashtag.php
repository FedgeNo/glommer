<?php

declare(strict_types=1);

/**
 * A post's #hashtags: the normalized Hashtags/PostHashtags tables (relational
 * integrity, per-tag lookups and counts, and the basis for trending) plus the
 * flat, FULLTEXT-searchable Posts.keywords copy. Tags are extracted from the
 * post body on create (Delta::hashtags) and stored lowercased.
 */
class Hashtag
{
    // A post with more than this many distinct hashtags indexes NONE of them
    // (and is auto-reported) - the body still renders every #tag as a link, so
    // real creative use stays unrestricted, but a spammer can't stuff a post
    // with tags to game tag pages/trending. 30 matches the usual creative max.
    public const MAX_HASHTAGS = 30;

    // The auto-report's reporter: the primary admin (system) account.
    private const SYSTEM_REPORTER_ID = 1;

    /**
     * Applies the hashtag policy to a freshly-created post: extracts its tags
     * and, if there are a sane number, indexes them; if there are too many
     * (spam), indexes none and auto-reports the post for moderation. The post
     * body still linkifies every tag regardless - this only governs indexing.
     * Backfill passes $auto_report = false (no flood of reports over old posts).
     *
     * @param array[] $ops the post's Delta ops
     */
    public static function indexPost(int $post_id, array $ops, bool $auto_report = true): void
    {
        $tags = Delta::hashtags($ops);

        if (count($tags) > self::MAX_HASHTAGS) {
            if ($auto_report) {
                Report::create(self::SYSTEM_REPORTER_ID, 'post', $post_id, 'Automatic: excessive hashtags (' . count($tags) . ' tags)');
            }

            return;
        }

        self::attach($post_id, $tags);
    }

    /**
     * Records a post's tags: upserts each into Hashtags, links them via
     * PostHashtags, and sets the post's flat keywords copy. Idempotent - safe to
     * re-run (INSERT IGNORE on the join, upsert on the tag), which the backfill
     * relies on.
     *
     * @param string[] $tags lowercased tags (Delta::hashtags output)
     */
    public static function attach(int $post_id, array $tags): void
    {
        if ($tags === []) {
            return;
        }

        $mysqli = Database::connection();

        foreach ($tags as $tag) {
            $tag_stmt = mysqli_prepare($mysqli, '
INSERT INTO `Hashtags` (`tag`)
    VALUES (?)
    ON DUPLICATE KEY UPDATE `hashtagId` = LAST_INSERT_ID(`hashtagId`)
');
            mysqli_stmt_bind_param($tag_stmt, 's', $tag);
            mysqli_stmt_execute($tag_stmt);
            $hashtag_id = (int) mysqli_insert_id($mysqli);

            $link_stmt = mysqli_prepare($mysqli, '
INSERT IGNORE INTO `PostHashtags` (`postId`, `hashtagId`)
    VALUES (?, ?)
');
            mysqli_stmt_bind_param($link_stmt, 'ii', $post_id, $hashtag_id);
            mysqli_stmt_execute($link_stmt);
        }

        $keywords = self::packKeywords($tags);

        $keywords_stmt = mysqli_prepare($mysqli, '
UPDATE `Posts`
    SET `keywords` = ?
    WHERE `postId` = ?
');
        mysqli_stmt_bind_param($keywords_stmt, 'si', $keywords, $post_id);
        mysqli_stmt_execute($keywords_stmt);
    }

    /**
     * Space-joins tags into the keywords column, stopping at a whole-tag
     * boundary before its varchar(255) limit (the PostHashtags rows keep the
     * complete set; keywords is a best-effort denormalized copy for search).
     *
     * @param string[] $tags
     */
    private static function packKeywords(array $tags): string
    {
        $keywords = '';

        foreach ($tags as $tag) {
            $candidate = $keywords === '' ? $tag : $keywords . ' ' . $tag;

            if (strlen($candidate) > 255) {
                break;
            }

            $keywords = $candidate;
        }

        return $keywords;
    }

    /**
     * Top-level posts carrying $tag, newest first, cursor-paginated exactly like
     * Post::globalFeedRows (limit+1/hasMore, banned authors excluded). Same
     * shape so the tag page reuses the feed rendering.
     *
     * @return array{rows: array[], hasMore: bool}
     */
    public static function postRows(string $tag, int $limit, ?int $before_post_id = null): array
    {
        $mysqli = Database::connection();
        $fetch_limit = $limit + 1;
        $not_banned = 0;

        if ($before_post_id !== null) {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`tag` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'siii', $tag, $not_banned, $before_post_id, $fetch_limit);
        } else {
            $stmt = mysqli_prepare($mysqli, '
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`tag` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
');
            mysqli_stmt_bind_param($stmt, 'sii', $tag, $not_banned, $fetch_limit);
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
     * The most-used tags all-time (by count of the top-level, non-banned posts
     * that carry them - matching what a tag page shows), for the /tags/ index.
     *
     * @return array<int, array{tag: string, postCount: int}>
     */
    public static function popular(int $limit): array
    {
        $not_banned = 0;

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `Hashtags`.`tag`, COUNT(*) AS `postCount`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    GROUP BY `Hashtags`.`hashtagId`
    ORDER BY `postCount` DESC, `Hashtags`.`tag` ASC
    LIMIT ?
');
        mysqli_stmt_bind_param($stmt, 'ii', $not_banned, $limit);

        return self::countRows($stmt);
    }

    /**
     * The tags used most in the last week's top-level, non-banned posts - a
     * simple recent-window "trending" (the timer-recomputed trending-entities
     * engine will supersede this later). Empty when nothing's been posted lately.
     *
     * @return array<int, array{tag: string, postCount: int}>
     */
    public static function trending(int $limit): array
    {
        $not_banned = 0;
        $days = 7;

        $stmt = mysqli_prepare(Database::connection(), '
SELECT `Hashtags`.`tag`, COUNT(*) AS `postCount`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`createdAt` >= NOW() - INTERVAL ? DAY
    GROUP BY `Hashtags`.`hashtagId`
    ORDER BY `postCount` DESC, `Hashtags`.`tag` ASC
    LIMIT ?
');
        mysqli_stmt_bind_param($stmt, 'iii', $not_banned, $days, $limit);

        return self::countRows($stmt);
    }

    /**
     * @return array<int, array{tag: string, postCount: int}>
     */
    private static function countRows(\mysqli_stmt $stmt): array
    {
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $tags = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $tags[] = ['tag' => (string) $row['tag'], 'postCount' => (int) $row['postCount']];
        }

        return $tags;
    }

    /**
     * Backfills hashtags for posts created before the feature: extracts each
     * post's tags from its stored Delta and attaches them. Idempotent (attach
     * uses INSERT IGNORE / upsert), so a re-run just re-confirms existing links.
     * Run once from the installer after the tables exist.
     */
    public static function backfill(): void
    {
        $mysqli = Database::connection();

        $result = mysqli_query($mysqli, '
SELECT `postId`, `descriptionDelta`
    FROM `Posts`
    WHERE `descriptionDelta` IS NOT NULL
');

        $posts = [];

        while ($row = mysqli_fetch_assoc($result)) {
            $posts[] = $row;
        }

        foreach ($posts as $row) {
            self::indexPost((int) $row['postId'], Delta::decode((string) $row['descriptionDelta']), false);
        }
    }
}
