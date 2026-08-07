<?php

declare(strict_types=1);

/**
 * Candidates for the nearby feed are found through boxes the
 * (latitude, longitude) index answers, widening until one provably holds the
 * k nearest - so what must not change is the answer itself: exactly the posts
 * a ranking of the whole table would have picked, whatever box they were
 * found in.
 *
 * Every assertion therefore compares the feed against an independent
 * reference computed in PHP from the same rows - the naive definition the
 * boxes exist to avoid. The shared test database can hold whatever earlier
 * tests put there; both sides see the same world.
 */
class NearbyFeedTest extends DatabaseTestCase
{
    /**
     * A page-sized k, so a handful of rows exercises the box arithmetic.
     * Public because the list subclass below reads it from its own scope.
     */
    public const TEST_LIMIT = 3;

    private static function locatedPost(float $latitude, float $longitude): int
    {
        $user_id = self::createUser();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'located', json_encode([['insert' => "located\n"]]));

        $post_id = (int) mysqli_insert_id(DB::connection());

        DB::run('
INSERT INTO `PostLocations` (`postId`, `latitude`, `longitude`)
    VALUES (?, ?, ?)
', 'idd', $post_id, $latitude, $longitude);

        return $post_id;
    }

    /** @return int[] the postIds the feed shows, via a k of TEST_LIMIT */
    private function feedResults(float $latitude, float $longitude): array
    {
        $list = new class(['latitude' => $latitude, 'longitude' => $longitude]) extends NearbyFeedList {
            public const NEAREST_LIMIT = NearbyFeedTest::TEST_LIMIT;
        };

        return array_map(static fn ($post): int => (int) $post -> postId, $list -> items);
    }

    /**
     * The definition itself, written the slow way: every located post ranked
     * by great-circle distance in PHP, the nearest TEST_LIMIT kept, anything
     * past the range cap dropped, then newest first - membership by
     * distance, order by time.
     *
     * @return int[]
     */
    private function referenceResults(float $latitude, float $longitude): array
    {
        $rows = DB::rows('
SELECT `postId`, `latitude`, `longitude`
    FROM `PostLocations`
', \stdClass::class);

        $distances = [];

        foreach ($rows as $row) {
            $distances[(int) $row -> postId] = acos(min(1.0,
                cos(deg2rad($latitude)) * cos(deg2rad((float) $row -> latitude)) * cos(deg2rad((float) $row -> longitude) - deg2rad($longitude))
                + sin(deg2rad($latitude)) * sin(deg2rad((float) $row -> latitude))
            ));
        }

        asort($distances);
        $near_enough = array_filter($distances, static fn (float $d): bool => $d <= NearbyFeedList::MAX_DISTANCE_KM / 6371);
        $nearest = array_slice(array_keys($near_enough), 0, self::TEST_LIMIT);
        rsort($nearest);

        return $nearest;
    }

    public function testACloseClusterBeatsTheFarSideOfTheWorld(): void
    {
        // Three posts within walking distance of the query point - the first,
        // tightest box holds them all and proves itself - and one in another
        // hemisphere that must be cut by distance, not luck.
        self::locatedPost(49.28, -123.09);
        self::locatedPost(49.29, -123.10);
        self::locatedPost(49.27, -123.08);
        self::locatedPost(-33.87, 151.21);

        $this -> assertSame($this -> referenceResults(49.28, -123.09), $this -> feedResults(49.28, -123.09));
    }

    public function testTheAntimeridianIsNotAWall(): void
    {
        // A box centred near longitude 180 wraps: posts a few kilometres away
        // across the line must beat one a thousand kilometres away on the
        // same side.
        self::locatedPost(0.0, -179.95);
        self::locatedPost(0.05, -179.90);
        self::locatedPost(-0.05, 179.95);
        self::locatedPost(0.0, 170.0);

        $this -> assertSame($this -> referenceResults(0.0, 179.9), $this -> feedResults(0.0, 179.9));
    }

    public function testAnEmptyRegionIsHonestlyEmpty(): void
    {
        // Nothing within the hour's-drive line means an empty page - never
        // the far side of the world dressed up as local.
        self::locatedPost(10.0, 10.0);

        $this -> assertSame([], $this -> feedResults(-75.0, -100.0));
        $this -> assertSame([], $this -> referenceResults(-75.0, -100.0), 'the reference must agree that nothing qualifies');
    }

    public function testTheHeadingNamesWhereNearbyIs(): void
    {
        DB::run('
INSERT INTO `Places` (`placeId`, `title`, `region`, `country`, `latitude`, `longitude`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'isssdd', 903000001, 'Kingston', 'Ontario', 'Canada', 44.2312, -76.4860);

        // A post nearby, so the section genuinely renders its list and the
        // heading over it.
        self::locatedPost(44.23, -76.48);

        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $section = new NearbyFeedSection(['latitude' => 44.23, 'longitude' => -76.48]);
        $element = $section -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        $heading = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//h2', $element) -> item(0);

        $this -> assertSame('Near Kingston, Ontario, Canada', $heading -> textContent);
    }

    public function testAPoleDoesNotBreakTheBoxes(): void
    {
        // Near a pole the longitude span of a box exceeds the globe and the
        // predicate has to drop away; a box that kept it would be a sliver
        // that misses posts sitting a few kilometres away across the meridians.
        self::locatedPost(89.5, 10.0);
        self::locatedPost(89.5, -170.0);
        self::locatedPost(89.4, 100.0);

        $this -> assertSame($this -> referenceResults(89.6, 0.0), $this -> feedResults(89.6, 0.0));
    }
}
