<?php

declare(strict_types=1);

/**
 * The /tags/ "Popular" section: the most-used tags all-time, by post count.
 * Server-rendered as a plain <ul> of tag links - a readable, crawlable list
 * that works with no JS and stays a scrollable list on narrow screens - which
 * tag-graph.js upgrades in place to a 3D force-directed graph above the layout
 * breakpoint (tags that share more posts spring together, drag to rotate). It
 * stays a list below the breakpoint because the graph captures touch and wheel
 * to rotate/zoom, which would trap the page's scroll on a phone.
 *
 * The nodes are read from the materialized PopularHashtags table (never
 * aggregated at read time); the co-occurrence edges between them are cheap to
 * derive live, bounded to the handful of nodes shown, and ride on the section's
 * data-edges attribute (JSON) so they survive DOMDocument's escaping and the
 * browser hands them back intact via dataset. hashtagId is carried on each node
 * only so the edges index against node order.
 */
class HashtagGraphList extends ItemList
{
    public ?string $class = 'HashtagGraphField';

    /** The graph shows this many tags and stops - there is no next page. */
    public const PAGE_SIZE = 50;

    // Read-path self-heal (mirrors Trending): once the last recompute is older
    // than this, a lottery-picked read recomputes, so the list degrades to
    // stale-but-self-healing rather than going dark if the timer isn't
    // installed.
    private const STALE_MINUTES = 30;
    private const RECOMPUTE_LOTTERY_ODDS = 20;
    private const LAST_RUN_SETTING = 'popularHashtagsRecomputedAt';

    // How many tags the recompute keeps - comfortably above what any /tags/
    // render asks for, so a read's LIMIT always has headroom.
    private const STORED = 100;

    protected function rows(): array
    {
        if (self::isStale() && mt_rand(1, self::RECOMPUTE_LOTTERY_ODDS) === 1) {
            self::recompute();
        }

        return DB::rows('
SELECT `hashtagId`, `slug`, `title`, `postCount`
    FROM `PopularHashtags`
    ORDER BY `postCount` DESC, `slug` ASC
    LIMIT ?
', 'HashtagNode', 'i', static::PAGE_SIZE);
    }

    /**
     * @return array<string, string>
     */
    protected function dataAttributes(): array
    {
        return ['data-edges' => (string) json_encode(self::edgesFor($this -> items))];
    }

    /**
     * Recomputes the all-time most-used tags (by count of the top-level,
     * non-banned posts that carry them) into PopularHashtags. Every kept tag is
     * stamped with the same computedAt; anything that fell out of the set is
     * deleted after, so a reader never sees a momentarily-empty table.
     */
    public static function recompute(): void
    {
        $not_banned = 0;
        $stored = self::STORED;

        $rows = DB::rows('
SELECT `Hashtags`.`hashtagId`, `Hashtags`.`slug`, `Hashtags`.`title`, COUNT(*) AS `postCount`
    FROM `PostHashtags`
    JOIN `Hashtags` ON `Hashtags`.`hashtagId` = `PostHashtags`.`hashtagId`
    JOIN `Posts` ON `Posts`.`postId` = `PostHashtags`.`postId`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`parentId` IS NULL AND `Users`.`banned` = ?
    GROUP BY `Hashtags`.`hashtagId`
    ORDER BY `postCount` DESC, `Hashtags`.`slug` ASC
    LIMIT ?
', 'HashtagNode', 'ii', $not_banned, $stored);

        $computed_at = date('Y-m-d H:i:s');

        foreach ($rows as $row) {
            DB::run('
INSERT INTO `PopularHashtags` (`hashtagId`, `slug`, `title`, `postCount`, `computedAt`)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`), `title` = VALUES(`title`), `postCount` = VALUES(`postCount`), `computedAt` = VALUES(`computedAt`)
', 'issis', $row -> hashtagId, $row -> slug, $row -> title, $row -> postCount, $computed_at);
        }

        DB::run('
DELETE
    FROM `PopularHashtags`
    WHERE `computedAt` < ?
', 's', $computed_at);

        Settings::set(self::LAST_RUN_SETTING, $computed_at);
    }

    /**
     * The co-occurrence edges among the given nodes: how many top-level posts
     * each pair shares, from PostHashtags. Bounded to the node set on both ends
     * (a handful of tags), so the self-join stays cheap. Edge endpoints are
     * indices into $nodes.
     *
     * @param HashtagNode[] $nodes
     * @return array<int, array{a: int, b: int, weight: int}>
     */
    private static function edgesFor(array $nodes): array
    {
        if (count($nodes) < 2) {
            return [];
        }

        $index_of = [];

        foreach ($nodes as $i => $node) {
            $index_of[(int) $node -> hashtagId] = $i;
        }

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

        $edges = [];

        while ($row = mysqli_fetch_assoc($edge_result)) {
            $a = $index_of[(int) $row['aId']] ?? null;
            $b = $index_of[(int) $row['bId']] ?? null;

            if ($a !== null && $b !== null) {
                $edges[] = ['a' => $a, 'b' => $b, 'weight' => (int) $row['weight']];
            }
        }

        return $edges;
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
