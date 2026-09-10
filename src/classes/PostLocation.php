<?php

declare(strict_types=1);

/**
 * A post's place, from the PostLocations side table. Hydrated in batch by
 * Post::fromRowsWithItems - one query per page of posts rather than one per
 * post, the same way a page's feed items and authors are loaded.
 */
class PostLocation
{
    public ?int $postId = null;
    public ?float $latitude = null;
    public ?float $longitude = null;
    public ?int $placeId = null;
    public int $placeResolved = 0;

    /** Resolve the name once, in the same transaction that saves the post. */
    public static function save(int $post_id, float $latitude, float $longitude): void
    {
        // Match the precision actually stored, including on later backfills.
        $latitude = round($latitude, 7);
        $longitude = round($longitude, 7);
        $place_id = Place::nearest($latitude, $longitude) ?-> placeId;

        DB::run('
INSERT INTO `PostLocations` (`postId`, `latitude`, `longitude`, `placeId`, `placeResolved`)
    VALUES (?, ?, ?, ?, 1)
    ON DUPLICATE KEY UPDATE `latitude` = VALUES(`latitude`), `longitude` = VALUES(`longitude`),
        `placeId` = VALUES(`placeId`), `placeResolved` = 1
', 'iddi', $post_id, $latitude, $longitude, $place_id);
    }

    /** Installer backfill; completed misses are skipped on every later run. */
    public static function resolvePending(): int
    {
        $resolved = 0;

        do {
            $rows = DB::rows('
SELECT `postId`, `latitude`, `longitude`
    FROM `PostLocations`
    WHERE `placeResolved` = 0
    ORDER BY `postId`
    LIMIT 200
', self::class);

            foreach ($rows as $row) {
                $place_id = Place::nearest($row -> latitude, $row -> longitude) ?-> placeId;
                $update = DB::run('
UPDATE `PostLocations`
    SET `placeId` = ?, `placeResolved` = 1
    WHERE `postId` = ? AND `latitude` = ? AND `longitude` = ? AND `placeResolved` = 0
', 'iidd', $place_id, $row -> postId, $row -> latitude, $row -> longitude);
                $resolved += mysqli_stmt_affected_rows($update);
            }
        } while ($rows !== []);

        return $resolved;
    }

    /** A changed gazetteer can change both successful and unsuccessful answers. */
    public static function invalidatePlaces(): void
    {
        DB::run('UPDATE `PostLocations` SET `placeId` = NULL, `placeResolved` = 0');
    }

    /**
     * Coordinates for a page of posts, keyed by postId. Posts without a
     * location are simply absent, so a caller reads it as "?? null".
     *
     * @param int[] $post_ids
     * @return array<int, array{latitude: float, longitude: float, placeLabel: ?string}>
     */
    public static function forPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $rows = DB::rows('
SELECT `l`.`postId`, `l`.`latitude`, `l`.`longitude`, `p`.`placeId`, `p`.`title`, `p`.`region`, `p`.`country`
    FROM `PostLocations` `l`
    LEFT JOIN `Places` `p` ON `p`.`placeId` = `l`.`placeId` AND `l`.`placeResolved` = 1
    WHERE `l`.`postId` IN (' . $placeholders . ')
', \stdClass::class, str_repeat('i', count($post_ids)), ...$post_ids);

        $locations = [];

        foreach ($rows as $row) {
            $place = new Place();
            $place -> title = $row -> title;
            $place -> region = $row -> region;
            $place -> country = $row -> country;

            $locations[(int) $row -> postId] = [
                'latitude' => (float) $row -> latitude,
                'longitude' => (float) $row -> longitude,
                'placeLabel' => $row -> placeId === null ? null : $place -> label(),
            ];
        }

        return $locations;
    }
}
