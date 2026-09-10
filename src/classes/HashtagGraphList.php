<?php

declare(strict_types=1);

/**
 * The /tags/ "Popular" section: the most-used tags all-time, by post count.
 * Server-rendered as a plain <ul> of tag links - a readable, crawlable list
 * that works with no JS and stays a scrollable list on narrow screens - which
 * Controllers.js's HashtagGraphList upgrades in place to a 3D force-directed graph above the layout
 * breakpoint (tags that share more posts spring together, drag to rotate). It
 * stays a list below the breakpoint because the graph captures touch and wheel
 * to rotate/zoom, which would trap the page's scroll on a phone.
 *
 * The nodes are read from the materialized PopularHashtags table (never
 * aggregated at read time); their co-occurrence edges are stored alongside
 * them in PopularHashtagEdges and ride on the section's
 * data-edges attribute (JSON) so they survive DOMDocument's escaping and the
 * browser hands them back intact via dataset. hashtagId is carried on each node
 * only so the edges index against node order.
 */
class HashtagGraphList extends ItemList
{
    public ?string $class = 'HashtagGraphList';

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
     * non-banned posts that carry them) and their co-occurrence edges. Both
     * tables are replaced in one transaction, retaining the previous graph
     * until its replacement commits.
     */
    public static function recompute(): void
    {
        DB::transaction(static fn () => self::rebuild());
    }

    /** Publish the nodes and their connections as one complete generation. */
    private static function rebuild(): void
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
        $edges = self::countEdges(array_map(static fn (HashtagNode $node): int => (int) $node -> hashtagId, $rows));

        // The transaction keeps the previous generation visible until commit;
        // the FK cascade removes its edges, including pairs now at zero.
        DB::run('DELETE FROM `PopularHashtags`');

        foreach ($rows as $row) {
            DB::run('
INSERT INTO `PopularHashtags` (`hashtagId`, `slug`, `title`, `postCount`, `computedAt`)
    VALUES (?, ?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE `slug` = VALUES(`slug`), `title` = VALUES(`title`), `postCount` = VALUES(`postCount`), `computedAt` = VALUES(`computedAt`)
', 'issis', $row -> hashtagId, $row -> slug, $row -> title, $row -> postCount, $computed_at);
        }

        foreach ($edges as $edge) {
            DB::run('
INSERT INTO `PopularHashtagEdges` (`aId`, `bId`, `weight`)
    VALUES (?, ?, ?)
', 'iii', $edge -> aId, $edge -> bId, $edge -> weight);
        }

        Settings::set(self::LAST_RUN_SETTING, $computed_at);
    }

    /** @param int[] $ids @return object[] */
    private static function countEdges(array $ids): array
    {
        if (count($ids) < 2) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        return DB::rows('
SELECT `a`.`hashtagId` AS `aId`, `b`.`hashtagId` AS `bId`, COUNT(*) AS `weight`
    FROM `PostHashtags` `a`
    JOIN `PostHashtags` `b` ON `b`.`postId` = `a`.`postId` AND `a`.`hashtagId` < `b`.`hashtagId`
    WHERE `a`.`hashtagId` IN (' . $placeholders . ') AND `b`.`hashtagId` IN (' . $placeholders . ')
    GROUP BY `a`.`hashtagId`, `b`.`hashtagId`
', 'stdClass', str_repeat('i', count($ids) * 2), ...$ids, ...$ids);
    }

    /**
     * Stored co-occurrence counts among the given nodes. Edge endpoints in the
     * browser payload are indices into $nodes, rather than database IDs.
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
SELECT `aId`, `bId`, `weight`
    FROM `PopularHashtagEdges`
    WHERE `aId` IN (' . $placeholders . ') AND `bId` IN (' . $placeholders . ')
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
