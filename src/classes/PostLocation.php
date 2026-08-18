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

    /**
     * Coordinates for a page of posts, keyed by postId. Posts without a
     * location are simply absent, so a caller reads it as "?? null".
     *
     * @param int[] $post_ids
     * @return array<int, array{latitude: float, longitude: float}>
     */
    public static function forPosts(array $post_ids): array
    {
        if ($post_ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($post_ids), '?'));

        $rows = DB::rows('
SELECT `postId`, `latitude`, `longitude`
    FROM `PostLocations`
    WHERE `postId` IN (' . $placeholders . ')
', \stdClass::class, str_repeat('i', count($post_ids)), ...$post_ids);

        $locations = [];

        foreach ($rows as $row) {
            $locations[(int) $row -> postId] = [
                'latitude' => (float) $row -> latitude,
                'longitude' => (float) $row -> longitude,
            ];
        }

        return $locations;
    }
}
