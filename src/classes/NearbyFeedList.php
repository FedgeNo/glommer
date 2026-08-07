<?php

declare(strict_types=1);

/**
 * The /nearby page: posts closest to a point, newest first.
 *
 * Membership is k-nearest AND within an hour's drive: the nearest ~2·sqrt(N)
 * of the located posts (see candidateLimit - generous while sparse, tighter
 * as density grows), cut at MAX_DISTANCE_KM so a sparse corpus never passes
 * off another province as nearby. An honestly short page beats a full one
 * of somewhere else.
 *
 * Distance decides membership only; the page is then ordered by postId so it
 * reads like an ordinary timeline. Ranking by distance would put an older post
 * above a newer one for being forty metres closer, which is not what anyone
 * wants from a feed.
 *
 * Build with new NearbyFeedList(['latitude' => 49.28, 'longitude' => -123.09]).
 */
class NearbyFeedList extends FeedList
{
    /**
     * The ceiling on how many of the closest posts are eligible, before the
     * newest-first cut. The working number grows with the corpus (see
     * candidateLimit); this cap keeps a huge corpus from turning the distance
     * pass into the whole table anyway.
     */
    public const NEAREST_LIMIT = 2000;

    /**
     * However few candidates there are, past this it is not "nearby" - if
     * you'd drive more than an hour, it's somewhere else. Vancouver's page
     * showing Ontario was k-nearest being honest about a sparse corpus, and
     * honestly empty beats confidently wrong here.
     */
    public const MAX_DISTANCE_KM = 100;

    /** Earth's mean radius, for turning the cap into radians of arc. */
    private const EARTH_RADIUS_KM = 6371;

    /**
     * How many nearest posts are eligible: twice the square root of the
     * located corpus, floored at a page, capped at NEAREST_LIMIT.
     *
     * Square-root, not a percentage: a percentage loosens "nearby" at
     * exactly the rate the corpus grows, so a big spread-out site ends up
     * calling a continent local. Root growth is the standard k-NN heuristic
     * for the same tension - generous while posts are sparse (a small site's
     * whole corpus is one page, and the floor serves all of it), tightening
     * relatively as density rises (four hundred posts field forty
     * candidates, ten thousand field two hundred), with no cliff anywhere on
     * the way to the cap.
     */
    protected function candidateLimit(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `PostLocations`
', 'PostCountData');

        return (int) min(static::NEAREST_LIMIT, max(static::PAGE_SIZE, (int) ceil(2 * sqrt((int) $row -> total))));
    }

    public ?float $latitude = null;
    public ?float $longitude = null;

    protected function rows(): array
    {
        $nearest_ids = $this -> nearestPostIds();

        if ($nearest_ids === []) {
            return [];
        }

        $not_banned = 0;
        $viewer_id = (int) Auth::id();
        $placeholders = implode(', ', array_fill(0, count($nearest_ids), '?'));

        return Post::fromRowsWithItems(DB::rows('
SELECT `Posts`.*,
    (SELECT COUNT(*) FROM `Posts` `replies` WHERE `replies`.`parentId` = `Posts`.`postId`) AS `replyCount`,
    (SELECT COUNT(*) FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId`) AS `likeCount`,
    EXISTS(SELECT 1 FROM `Likes` WHERE `Likes`.`postId` = `Posts`.`postId` AND `Likes`.`userId` = ?) AS `liked`,
    EXISTS(SELECT 1 FROM `Bookmarks` WHERE `Bookmarks`.`postId` = `Posts`.`postId` AND `Bookmarks`.`userId` = ?) AS `bookmarked`
    FROM `Posts`
    JOIN `Users` ON `Users`.`userId` = `Posts`.`userId`
    WHERE `Posts`.`postId` IN (' . $placeholders . ') AND `Users`.`banned` = ?
    ORDER BY `Posts`.`postId` DESC
    LIMIT ? OFFSET ?
', 'Post', 'ii' . str_repeat('i', count($nearest_ids)) . 'iii', $viewer_id, $viewer_id, ...[...$nearest_ids, $not_banned, static::PAGE_SIZE + 1, $this -> offset]));
    }

    /**
     * The eligible posts: the candidateLimit() closest, of those within
     * MAX_DISTANCE_KM. One box query answers it exactly - a box whose
     * inradius exceeds the range cap contains everything that could
     * qualify, so there is nothing beyond it to miss. The (latitude,
     * longitude) index serves the box; the great-circle ordering and the
     * range cut run over only what it holds.
     *
     * @return int[]
     */
    private function nearestPostIds(): array
    {
        // 1.05: a shade over the exact degrees-per-km conversion, so the
        // box's inradius stays safely outside the range cap.
        $half_width = self::MAX_DISTANCE_KM / 111.0 * 1.05;
        $cutoff = self::MAX_DISTANCE_KM / self::EARTH_RADIUS_KM;

        $near = array_filter(
            $this -> candidatesInBox($half_width, $this -> candidateLimit()),
            static fn (object $row): bool => (float) $row -> distance <= $cutoff
        );

        return array_map(static fn (object $row): int => (int) $row -> postId, $near);
    }

    /**
     * The closest $limit posts within a box, nearest first, each with its
     * angular distance.
     *
     * LEAST(1, ...) clamps the cosine before ACOS: floating point can push an
     * exact-match point a hair above 1, where ACOS returns NULL and the post
     * silently vanishes from the feed.
     *
     * @return object[]
     */
    private function candidatesInBox(float $half_width, int $limit): array
    {
        $distance = 'ACOS(LEAST(1,
                COS(RADIANS(?)) * COS(RADIANS(`latitude`)) * COS(RADIANS(`longitude`) - RADIANS(?))
                + SIN(RADIANS(?)) * SIN(RADIANS(`latitude`))
            ))';

        // The latitude band is what the index serves; longitude then filters
        // within it. The longitude span widens by the cosine of the latitude
        // so the box never narrows below the range it stands for, and near a
        // pole (or once it spans the globe) the predicate is simply dropped -
        // every longitude is close there.
        $where = ' WHERE `latitude` BETWEEN ? AND ?';
        $types = 'ddddd';
        $parameters = [
            $this -> latitude,
            $this -> longitude,
            $this -> latitude,
            max(-90.0, $this -> latitude - $half_width),
            min(90.0, $this -> latitude + $half_width),
        ];

        $lng_half_width = $half_width / max(cos(deg2rad($this -> latitude)), 1e-9);

        // No longitude predicate once the box's latitude band touches a pole:
        // up there, somewhere an hour away can sit at any longitude at all,
        // and the cosine-scaled span stops being a bound on anything.
        if ($lng_half_width < 180.0 && abs($this -> latitude) + $half_width < 90.0) {
            $lng_min = $this -> longitude - $lng_half_width;
            $lng_max = $this -> longitude + $lng_half_width;

            if ($lng_min < -180.0) {
                // The box crosses the antimeridian: one range on each side.
                $where .= ' AND (`longitude` >= ? OR `longitude` <= ?)';
                $parameters[] = $lng_min + 360.0;
                $parameters[] = $lng_max;
            } elseif ($lng_max > 180.0) {
                $where .= ' AND (`longitude` >= ? OR `longitude` <= ?)';
                $parameters[] = $lng_min;
                $parameters[] = $lng_max - 360.0;
            } else {
                $where .= ' AND `longitude` BETWEEN ? AND ?';
                $parameters[] = $lng_min;
                $parameters[] = $lng_max;
            }

            $types .= 'dd';
        }

        return DB::rows('
SELECT `postId`, ' . $distance . ' AS `distance`
    FROM `PostLocations`' . $where . '
    ORDER BY `distance`
    LIMIT ' . $limit . '
', \stdClass::class, $types, ...$parameters);
    }

    /**
     * Its own endpoint rather than api/feed.php, since proximity isn't one of
     * that endpoint's feed types. The origin rides along here: InfiniteScroller
     * sends every key that isn't endpoint/itemType/direction with each request,
     * so page two ranks from the same point page one did.
     *
     * @return array<string, mixed>
     */
    protected function scrollConfig(): ?array
    {
        return [
            'endpoint' => '/api/nearby-history',
            'itemType' => 'Post',
            'latitude' => $this -> latitude,
            'longitude' => $this -> longitude,
        ];
    }
}
