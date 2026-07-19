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
INSERT INTO `Hashtags` (`slug`, `title`)
    VALUES (?, ?)
    ON DUPLICATE KEY UPDATE `hashtagId` = LAST_INSERT_ID(`hashtagId`)
', 'ss', $tag, $tag);
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
