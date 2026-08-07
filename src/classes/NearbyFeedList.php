<?php

declare(strict_types=1);

/**
 * The /nearby page: posts closest to a point, newest first.
 *
 * Deliberately k-nearest rather than a radius search. A radius needs a constant
 * that is wrong at every scale - 50km shows an empty page on a quiet site and a
 * firehose on a busy one - whereas "the nearest ~2·sqrt(N) of the located
 * posts, whatever the distance" casts a wide net while there's little to find
 * and tightens to genuinely local content as density grows, with nothing to
 * tune (see candidateLimit).
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
     * newest-first cut. The working number is a tenth of all located posts
     * (see candidateLimit) - "nearby" should mean the nearest tenth of the
     * world this server knows, at any scale - and this cap keeps a huge
     * corpus from turning the distance pass into the whole table anyway.
     */
    public const NEAREST_LIMIT = 2000;

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

    /**
     * The latitude half-widths, in degrees, of the boxes candidates are looked
     * for in, nearest box first. Null is the whole earth - the terminal pass
     * that always answers, and the exact query this replaced. Each step covers
     * sixteen times the area of the one before, so even the widest miss costs
     * a handful of empty index ranges before falling through.
     */
    private const BOX_HALF_WIDTHS = [0.5, 2.0, 8.0, 32.0, null];

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
     * The candidateLimit() closest located posts - the nearest tenth of what
     * this server holds - found through boxes the (latitude, longitude) index
     * can answer rather than by ranking the whole table, which is what this
     * cost before, on every page of the feed, growing with every located post
     * ever made.
     *
     * Each box is tried with the same great-circle ordering the whole-earth
     * pass uses, so a box that holds enough candidates gives exactly the rows
     * the full ranking would have. Exactness is checked, not assumed: the box
     * only proves itself when the farthest candidate kept is nearer than the
     * box's own inradius - anything outside the box is necessarily farther
     * than that - and a page that fails the check falls through to a wider
     * box, ending at the whole earth.
     *
     * @return int[]
     */
    private function nearestPostIds(): array
    {
        $limit = $this -> candidateLimit();

        foreach (self::BOX_HALF_WIDTHS as $half_width) {
            $candidates = $this -> candidatesInBox($half_width, $limit);

            if ($half_width === null) {
                return array_map(static fn (object $row): int => (int) $row -> postId, $candidates);
            }

            if (count($candidates) < $limit) {
                continue;
            }

            // The distances are radians of arc, so the guard compares against
            // the box's half-width in the same unit. Latitude degrees are
            // degrees of arc everywhere on the sphere; the longitude span was
            // sized to be at least as wide.
            $farthest_kept = (float) end($candidates) -> distance;

            if ($farthest_kept <= deg2rad($half_width)) {
                return array_map(static fn (object $row): int => (int) $row -> postId, $candidates);
            }
        }

        return [];
    }

    /**
     * The closest $limit posts within a box, nearest first, each with its
     * angular distance. A null half-width is the whole earth.
     *
     * LEAST(1, ...) clamps the cosine before ACOS: floating point can push an
     * exact-match point a hair above 1, where ACOS returns NULL and the post
     * silently vanishes from the feed.
     *
     * @return object[]
     */
    private function candidatesInBox(?float $half_width, int $limit): array
    {
        $distance = 'ACOS(LEAST(1,
                COS(RADIANS(?)) * COS(RADIANS(`latitude`)) * COS(RADIANS(`longitude`) - RADIANS(?))
                + SIN(RADIANS(?)) * SIN(RADIANS(`latitude`))
            ))';

        $where = '';
        $types = 'ddd';
        $parameters = [$this -> latitude, $this -> longitude, $this -> latitude];

        if ($half_width !== null) {
            // The latitude band is what the index serves; longitude then
            // filters within it. The longitude span widens by the cosine of
            // the latitude so the box never narrows below the guard's radius,
            // and near a pole (or once it spans the globe) the predicate is
            // simply dropped - every longitude is close there.
            $where = ' WHERE `latitude` BETWEEN ? AND ?';
            $types .= 'dd';
            $parameters[] = max(-90.0, $this -> latitude - $half_width);
            $parameters[] = min(90.0, $this -> latitude + $half_width);

            $lng_half_width = $half_width / max(cos(deg2rad($this -> latitude)), 1e-9);

            if ($lng_half_width < 180.0) {
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
