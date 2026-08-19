<?php

declare(strict_types=1);

/**
 * The local gazetteer: coordinates in, a nameable place out - or null, which
 * callers render as bare coordinates. Null is a first-class answer here; the
 * one thing this class must never do is name somewhere that isn't honestly
 * near.
 */
class PlaceTest extends DatabaseTestCase
{
    private static int $next_place_id = 900000000;

    private static function place(string $title, string $region, string $country, float $latitude, float $longitude): int
    {
        $place_id = self::$next_place_id++;

        DB::run('
INSERT INTO `Places` (`placeId`, `title`, `region`, `country`, `latitude`, `longitude`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'isssdd', $place_id, $title, $region, $country, $latitude, $longitude);

        return $place_id;
    }

    public function testTheNearerOfTwoTownsNamesThePoint(): void
    {
        self::place('Kingston', 'Ontario', 'Canada', 44.2312, -76.4860);
        self::place('Gananoque', 'Ontario', 'Canada', 44.3301, -76.1610);

        // A point in Kingston's harbour: both towns are in the box, the
        // nearer one has to win.
        $this -> assertSame('Kingston', Place::nearest(44.22, -76.48) ?-> title);
    }

    public function testTheOpenOceanIsNotNamedAfterItsNearestCoast(): void
    {
        self::place('St. John\'s', 'Newfoundland and Labrador', 'Canada', 47.5615, -52.7126);

        // Mid-Atlantic, a thousand kilometres out: no name is the honest
        // answer, whatever is closest.
        $this -> assertNull(Place::nearest(45.0, -40.0));
    }

    public function testTheLabelReadsTitleRegionCountry(): void
    {
        self::place('Tuktoyaktuk', 'Northwest Territories', 'Canada', 69.4454, -133.0342);

        $this -> assertSame(
            'Tuktoyaktuk, Northwest Territories, Canada',
            Place::nearest(69.44, -133.03) ?-> label()
        );
    }

    public function testALabelDropsEmptyAndRepeatedParts(): void
    {
        // A city-state's region repeats its name, and some places have no
        // region at all - neither should read like a stutter.
        self::place('Singapore', '', 'Singapore', 1.28967, 103.85007);

        $this -> assertSame('Singapore', Place::nearest(1.29, 103.85) ?-> label());
    }

    public function testImportResolvesTheCodesToNames(): void
    {
        // The three files exactly as GeoNames ships them: places keyed to
        // admin1 codes and ISO country codes, resolved to names on the way in
        // so a lookup at render time is one table and no joins.
        $directory = sys_get_temp_dir() . '/glommer-place-test-' . bin2hex(random_bytes(4));
        mkdir($directory);

        file_put_contents($directory . '/cities.txt',
            "902000001\tSydney\tSydney\t\t-33.86785\t151.20732\tP\tPPLA\tAU\t\t02\t\t\t\t4627345\t\t19\tAustralia/Sydney\t2026-01-01\n"
            . "902000002\tNowhereville\tNowhereville\t\t10.0\t10.0\tP\tPPL\tXX\t\t99\t\t\t\t120\t\t5\tEtc/UTC\t2026-01-01\n");
        file_put_contents($directory . '/admin1.txt', "AU.02\tNew South Wales\tNew South Wales\t2155400\n");
        file_put_contents($directory . '/countries.txt',
            "# ISO\tISO3\tISO-Numeric\tfips\tCountry\n"
            . "AU\tAUS\t036\tAS\tAustralia\t\n");

        try {
            $this -> assertSame(2, Place::import($directory . '/cities.txt', $directory . '/admin1.txt', $directory . '/countries.txt'));

            $sydney = Place::nearest(-33.87, 151.21);
            $this -> assertSame('Sydney, New South Wales, Australia', $sydney ?-> label());

            // Codes with no entry in the companion files resolve to nothing,
            // not to a crash and not to the raw code.
            $this -> assertSame('Nowhereville', Place::nearest(10.0, 10.0) ?-> label());
        } finally {
            array_map('unlink', glob($directory . '/*'));
            rmdir($directory);
        }
    }

    public function testImportUpsertsOnTheGeoNamesId(): void
    {
        // A reload of a newer dump refreshes in place rather than duplicating
        // the planet.
        $directory = sys_get_temp_dir() . '/glommer-place-test-' . bin2hex(random_bytes(4));
        mkdir($directory);

        $row = fn (string $name): string => "902000003\t" . $name . "\t" . $name . "\t\t51.5\t-0.12\tP\tPPLC\tGB\t\t\t\t\t\t8961989\t\t25\tEurope/London\t2026-01-01\n";
        file_put_contents($directory . '/admin1.txt', '');
        file_put_contents($directory . '/countries.txt', "GB\tGBR\t826\tUK\tUnited Kingdom\t\n");

        try {
            file_put_contents($directory . '/cities.txt', $row('Londn'));
            Place::import($directory . '/cities.txt', $directory . '/admin1.txt', $directory . '/countries.txt');

            file_put_contents($directory . '/cities.txt', $row('London'));
            Place::import($directory . '/cities.txt', $directory . '/admin1.txt', $directory . '/countries.txt');

            $this -> assertSame('London', Place::nearest(51.5, -0.12) ?-> title);
        } finally {
            array_map('unlink', glob($directory . '/*'));
            rmdir($directory);
        }
    }

    public function testTheNearestCacheStaysBoundedAndStillAnswersAfterItsCap(): void
    {
        self::place('Cacheville', 'Test', 'Canada', 20.0, 20.0);
        (new \ReflectionProperty(Place::class, 'nearestByPoint')) -> setValue(null, []);

        for ($index = 0; $index <= 200; $index++) {
            $answer = Place::nearest(20.0 + $index / 100000, 20.0);
        }

        $cache = (new \ReflectionProperty(Place::class, 'nearestByPoint')) -> getValue();

        $this -> assertSame('Cacheville', $answer ?-> title);
        $this -> assertSame(200, count($cache));
    }
}
