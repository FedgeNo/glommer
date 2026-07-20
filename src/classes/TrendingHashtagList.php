<?php

declare(strict_types=1);

/**
 * The /tags/ "Trending" cloud: the tags used most in the last week's top-level,
 * non-banned posts, as a wrap of HashtagChips. Read from the materialized
 * TrendingHashtags table (never aggregated at read time); recomputed on a timer
 * (bin/compute-trending.php) or lazily via the read-path lottery self-heal.
 */
class TrendingHashtagList extends ItemList
{
    public ?string $class = 'TrendingHashtagList d-flex flex-wrap gap-2';

    /** The cloud shows this many tags and stops - there is no next page. */
    public const PAGE_SIZE = 50;

    // Read-path self-heal, same as HashtagGraphList / Trending.
    private const STALE_MINUTES = 30;
    private const RECOMPUTE_LOTTERY_ODDS = 20;
    private const LAST_RUN_SETTING = 'trendingHashtagsRecomputedAt';

    // The recency window and how many tags the recompute keeps.
    private const WINDOW_DAYS = 7;
    private const STORED = 100;

    protected function rows(): array
    {
        if (self::isStale() && mt_rand(1, self::RECOMPUTE_LOTTERY_ODDS) === 1) {
            self::recompute();
        }

        return DB::rows('
SELECT `slug`, `title`, `postCount`
    FROM `TrendingHashtags`
    ORDER BY `postCount` DESC, `slug` ASC
    LIMIT ?
', 'HashtagChip', 'i', static::PAGE_SIZE);
    }

    /**
     * Recomputes the last-WINDOW_DAYS most-used tags (by count of the
     * top-level, non-banned posts that carry them) into TrendingHashtags. Same
     * stamp-then-delete replacement as HashtagGraphList::recompute(), so a reader
     * never sees a momentarily-empty table.
     */
    public static function recompute(): void
    {
        $not_banned = 0;
        $days = self::WINDOW_DAYS;
        $stored = self::STORED;

        $rows = DB::rows('
SELECT `Hashtags`.`hashtagId`, `Hashtags`.`slug`, `Hashtags`.`title`, COUNT(*) AS `postCount`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ? AND `Posts`.`createdAt` >= NOW() - INTERVAL ? DAY
    GROUP BY `Hashtags`.`hashtagId`
    ORDER BY `postCount` DESC, `Hashtags`.`slug` ASC
    LIMIT ?
', 'HashtagNode', 'iii', $not_banned, $days, $stored);

        $computed_at = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            DB::run('
INSERT INTO `TrendingHashtags` (`hashtagId`, `slug`, `title`, `postCount`, `computedAt`)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`), `title` = VALUES(`title`), `postCount` = VALUES(`postCount`), `computedAt` = VALUES(`computedAt`)
', 'issis', $row -> hashtagId, $row -> slug, $row -> title, $row -> postCount, $computed_at);
        }

        DB::run('
DELETE
    FROM `TrendingHashtags`
    WHERE `computedAt` < ?
', 's', $computed_at);

        Settings::set(self::LAST_RUN_SETTING, $computed_at);
    }

    private static function isStale(): bool
    {
        $newest = Settings::get(self::LAST_RUN_SETTING);

        if ($newest === null) {
            return true;
        }

        return (time() - strtotime($newest)) > self::STALE_MINUTES * 60;
    }
}
