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
    // At most this many of a post's distinct hashtags are stored (in Hashtags/
    // PostHashtags and the flat keywords copy) and so count toward tag pages
    // and trending. A post may contain any number of #tags - they all still
    // render as links in the body - but only its first MAX_HASHTAGS get
    // indexed, so tag-stuffing can't pollute the index or game trending.
    public const MAX_HASHTAGS = 10;

    /**
     * Indexes a freshly-created post's hashtags: the first MAX_HASHTAGS of
     * them (see the constant) are stored; any beyond that are left unindexed.
     * The post body still linkifies every tag regardless - this only governs
     * indexing.
     *
     * @param array[] $ops the post's Delta ops
     */
    public static function indexPost(int $post_id, array $ops): void
    {
        self::attach($post_id, array_slice(Delta::hashtags($ops), 0, self::MAX_HASHTAGS));
    }

    /**
     * Like indexPost(), but for an EDITED post whose tags may have changed -
     * including removed entirely. attach()'s INSERT IGNORE is built for
     * one-shot creation, where nothing to remove exists yet, so it only ever
     * adds; a tag an edit dropped would stay linked forever without this
     * clearing every existing PostHashtags row for $post_id first. Also
     * handles the all-tags-removed case (attach() no-ops on an empty tag
     * list, which would leave a stale keywords value behind).
     *
     * @param array[] $ops the post's edited Delta ops
     */
    public static function reindexPost(int $post_id, array $ops): void
    {
        $tags = array_slice(Delta::hashtags($ops), 0, self::MAX_HASHTAGS);

        DB::run('
DELETE
    FROM `PostHashtags`
    WHERE `postId` = ?
', 'i', $post_id);

        if ($tags === []) {
            $empty_keywords = null;

            DB::run('
UPDATE `Posts`
    SET `keywords` = ?
    WHERE `postId` = ?
', 'si', $empty_keywords, $post_id);

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

        $mysqli = DB::connection();

        foreach ($tags as $tag) {
            DB::run('
INSERT INTO `Hashtags` (`tag`)
    VALUES (?)
    ON DUPLICATE KEY UPDATE `hashtagId` = LAST_INSERT_ID(`hashtagId`)
', 's', $tag);
            $hashtag_id = (int) mysqli_insert_id($mysqli);

            DB::run('
INSERT IGNORE INTO `PostHashtags` (`postId`, `hashtagId`)
    VALUES (?, ?)
', 'ii', $post_id, $hashtag_id);
        }

        $keywords = self::packKeywords($tags);

        DB::run('
UPDATE `Posts`
    SET `keywords` = ?
    WHERE `postId` = ?
', 'si', $keywords, $post_id);
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
        $fetch_limit = $limit + 1;
        $not_banned = 0;

        if ($before_post_id !== null) {
            $stmt = DB::run('
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`tag` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`postId` < ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'siii', $tag, $not_banned, $before_post_id, $fetch_limit);
        } else {
            $stmt = DB::run('
SELECT `Posts`.*
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Hashtags`.`tag` = ? AND `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ?
', 'sii', $tag, $not_banned, $fetch_limit);
        }

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

        $stmt = DB::prepare('
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
        DB::bind($stmt, 'ii', $not_banned, $limit);

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

        $stmt = DB::prepare('
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
        DB::bind($stmt, 'iii', $not_banned, $days, $limit);

        return self::countRows($stmt);
    }

    /**
     * The data for the /tags/ force-directed graph: the top $limit tags by
     * occurrence as nodes (each with its post count, which drives node size),
     * plus the co-occurrence edges between them (how many posts each pair shares,
     * from the PostHashtags relationship - the more shared, the tighter the
     * spring). Edge endpoints are indices into the returned nodes array.
     *
     * The edge self-join is bounded to the node set on both ends, so it stays
     * cheap at this scale; a much larger site would precompute co-occurrence
     * rather than self-join PostHashtags live.
     *
     * @return array{nodes: array<int, array{tag: string, postCount: int}>, edges: array<int, array{a: int, b: int, weight: int}>}
     */
    public static function graphData(int $limit): array
    {
        $not_banned = 0;

        $node_stmt = DB::run('
SELECT `Hashtags`.`hashtagId`, `Hashtags`.`tag`, COUNT(*) AS `postCount`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    GROUP BY `Hashtags`.`hashtagId`
    ORDER BY `postCount` DESC, `Hashtags`.`tag` ASC
    LIMIT ?
', 'ii', $not_banned, $limit);
        $node_result = mysqli_stmt_get_result($node_stmt);

        $nodes = [];
        $index_of = [];

        while ($row = mysqli_fetch_assoc($node_result)) {
            $index_of[(int) $row['hashtagId']] = count($nodes);
            $nodes[] = ['tag' => (string) $row['tag'], 'postCount' => (int) $row['postCount']];
        }

        $edges = [];

        if (count($nodes) > 1) {
            $ids = array_keys($index_of);
            $placeholders = implode(', ', array_fill(0, count($ids), '?'));

            // Both IN lists bind the same node ids.
            $bound = array_merge($ids, $ids);

            $edge_stmt = DB::run('
SELECT `a`.`hashtagId` AS `aId`, `b`.`hashtagId` AS `bId`, COUNT(*) AS `weight`
    FROM `PostHashtags` `a`
    JOIN `PostHashtags` `b` ON `b`.`postId` = `a`.`postId` AND `a`.`hashtagId` < `b`.`hashtagId`
    WHERE `a`.`hashtagId` IN (' . $placeholders . ') AND `b`.`hashtagId` IN (' . $placeholders . ')
    GROUP BY `a`.`hashtagId`, `b`.`hashtagId`
', str_repeat('i', count($bound)), ...$bound);
            $edge_result = mysqli_stmt_get_result($edge_stmt);

            while ($row = mysqli_fetch_assoc($edge_result)) {
                $a = $index_of[(int) $row['aId']] ?? null;
                $b = $index_of[(int) $row['bId']] ?? null;

                if ($a !== null && $b !== null) {
                    $edges[] = ['a' => $a, 'b' => $b, 'weight' => (int) $row['weight']];
                }
            }
        }

        return ['nodes' => $nodes, 'edges' => $edges];
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
        $mysqli = DB::connection();

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
            self::indexPost((int) $row['postId'], Delta::decode((string) $row['descriptionDelta']));
        }
    }
}
