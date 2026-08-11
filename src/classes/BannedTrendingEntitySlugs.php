<?php

declare(strict_types=1);

/**
 * Rewrites banned-topic slugs into the form an address uses.
 *
 * A ban is keyed on (type, slug), and Trending::isBanned() asks with the slug
 * built from the topic's name. A row whose slug was built by a different rule
 * answers "not banned" to every question, which reads exactly like no ban - so
 * this runs from the installer rather than being left to notice itself.
 *
 * Idempotent: a slug already in address form rewrites to itself and is skipped.
 */
class BannedTrendingEntitySlugs
{
    /** @return int how many rows were rewritten */
    public static function run(): int
    {
        $rows = DB::rows('
SELECT `type`, `slug`, `title`
    FROM `BannedTrendingEntities`
', 'stdClass');

        $rewritten = 0;

        foreach ($rows as $row) {
            // Derived from the title, which is the name as somebody wrote it.
            // That is what the slug was always derived from, so deriving it
            // again under the current rule is the whole of the fix.
            $wanted = topic_slug((string) $row -> title);

            if ($wanted === '' || $wanted === (string) $row -> slug) {
                continue;
            }

            // (type, slug) is the primary key. Where the wanted slug is already
            // taken, both rows ban the same topic and the one already in the
            // right place stands; this one is dropped rather than collided in.
            $taken = DB::row('
SELECT `slug`
    FROM `BannedTrendingEntities`
    WHERE `type` = ? AND `slug` = ?
', 'stdClass', 'ss', (string) $row -> type, $wanted);

            if ($taken !== null) {
                DB::run('
DELETE
    FROM `BannedTrendingEntities`
    WHERE `type` = ? AND `slug` = ?
', 'ss', (string) $row -> type, (string) $row -> slug);
            } else {
                DB::run('
UPDATE `BannedTrendingEntities`
    SET `slug` = ?
    WHERE `type` = ? AND `slug` = ?
', 'sss', $wanted, (string) $row -> type, (string) $row -> slug);
            }

            $rewritten++;
        }

        return $rewritten;
    }
}
