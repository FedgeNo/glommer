<?php

declare(strict_types=1);

/**
 * A town or city from the Places table - the GeoNames gazetteer this server
 * carries so a post's coordinates can be answered with a name locally,
 * without a request to anyone at render time.
 *
 * Everything here degrades to absence: an empty table (the dump not loaded
 * yet), a point in the ocean, a point merely far from anywhere - all answer
 * null, and the caller shows coordinates instead. A name is only ever offered
 * when somewhere real is genuinely close.
 */
#[\AllowDynamicProperties]
class Place
{
    /**
     * How far away a place may be and still name a point, in degrees of arc.
     * About 150km: beyond that, "near X" stops being true - a post from a
     * ship should say where it is, not the name of a coast it cannot see.
     */
    private const NAMING_LIMIT_DEGREES = 1.35;

    /**
     * The latitude half-widths of the boxes a place is looked for in. The
     * second is just past the naming limit, so anything it cannot see was
     * never nameable - there is no whole-earth pass here by design.
     */
    private const BOX_HALF_WIDTHS = [0.35, 1.5];

    public ?int $placeId = null;
    public ?string $title = null;
    public ?string $region = null;
    public ?string $country = null;
    public ?float $latitude = null;
    public ?float $longitude = null;

    /**
     * The nearest place to a point that is close enough to name it, or null.
     *
     * Found through boxes the (latitude, longitude) index answers, same as
     * the nearby feed: a candidate is only trusted when it is nearer than the
     * box's own inradius, since anything outside the box is necessarily
     * farther than that.
     */
    public static function nearest(float $latitude, float $longitude): ?Place
    {
        foreach (self::BOX_HALF_WIDTHS as $half_width) {
            $candidate = self::nearestInBox($latitude, $longitude, $half_width);

            if ($candidate === null) {
                continue;
            }

            $distance_degrees = rad2deg((float) $candidate -> distance);

            if ($distance_degrees > self::NAMING_LIMIT_DEGREES) {
                return null;
            }

            if ($distance_degrees <= $half_width) {
                return $candidate;
            }
        }

        return null;
    }

    /** "Town, Region, Country" - with empty and repeated parts dropped. */
    public function label(): string
    {
        $parts = [];

        foreach ([(string) $this -> title, (string) $this -> region, (string) $this -> country] as $part) {
            if ($part !== '' && $part !== end($parts)) {
                $parts[] = $part;
            }
        }

        return implode(', ', $parts);
    }

    private static function nearestInBox(float $latitude, float $longitude, float $half_width): ?Place
    {
        $where = '`latitude` BETWEEN ? AND ?';
        $types = 'dd';
        $parameters = [max(-90.0, $latitude - $half_width), min(90.0, $latitude + $half_width)];

        // The longitude span widens by the cosine of the latitude so the box
        // never narrows below its stated radius; near a pole (or spanning the
        // globe) the predicate drops away - every longitude is close there.
        $lng_half_width = $half_width / max(cos(deg2rad($latitude)), 1e-9);

        if ($lng_half_width < 180.0) {
            $lng_min = $longitude - $lng_half_width;
            $lng_max = $longitude + $lng_half_width;

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

        // LEAST(1, ...) clamps the cosine before ACOS: floating point can push
        // an exact match a hair above 1, where ACOS returns NULL.
        return DB::row('
SELECT `placeId`, `title`, `region`, `country`,
    ACOS(LEAST(1,
        COS(RADIANS(?)) * COS(RADIANS(`latitude`)) * COS(RADIANS(`longitude`) - RADIANS(?))
        + SIN(RADIANS(?)) * SIN(RADIANS(`latitude`))
    )) AS `distance`
    FROM `Places`
    WHERE ' . $where . '
    ORDER BY `distance`
    LIMIT 1
', 'Place', 'ddd' . $types, $latitude, $longitude, $latitude, ...$parameters);
    }

    public static function load(int $place_id): ?self
    {
        return DB::row('
SELECT `placeId`, `title`, `region`, `country`, `latitude`, `longitude`
    FROM `Places`
    WHERE `placeId` = ?
', self::class, 'i', $place_id);
    }

    /**
     * Places whose name starts with what was typed, biggest first - the
     * autocomplete behind the nearby page's search. Prefix match only, so
     * the title index serves it as a range walk; population order because
     * whoever types a city's name almost always means the big one.
     *
     * @return self[]
     */
    /**
     * The shortest prefix worth answering. One letter ranges a fifth of the
     * gazetteer and then sorts all of it by population to show eight rows -
     * measured at 122ms against 0.3ms for a prefix that narrows - and nobody
     * who has typed one letter has said which place they mean yet.
     */
    public const MINIMUM_QUERY_LENGTH = 3;

    public static function suggest(string $query, int $limit = 8): array
    {
        $query = trim($query);
        $length = mb_strlen($query);

        if ($length < self::MINIMUM_QUERY_LENGTH || $length > 100) {
            return [];
        }

        $prefix = addcslashes($query, '\\%_') . '%';

        return DB::rows('
SELECT `placeId`, `title`, `region`, `country`, `latitude`, `longitude`
    FROM `Places`
    WHERE `title` LIKE ?
    ORDER BY `population` DESC
    LIMIT ' . max(1, min(20, $limit)) . '
', self::class, 's', $prefix);
    }

    /**
     * Whether the gazetteer has been loaded at all - which is all anything
     * deciding whether to offer a place name needs to know.
     *
     * Counting the table to answer it walks every row in an index: a quarter of
     * a million of them once the cities file is in, for a question the first
     * row settles. The installer, which wants the number itself, still counts.
     */
    public static function any(): bool
    {
        return DB::row('
SELECT 1 AS `total`
    FROM `Places`
    LIMIT 1
', 'PostCountData') !== null;
    }

    public static function count(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Places`
', 'PostCountData');

        return $row === null ? 0 : (int) $row -> total;
    }

    /**
     * Loads the GeoNames dump into the Places table: the cities file itself,
     * with region and country codes resolved to names through the two
     * companion files, so a lookup at render time is one table and no joins.
     *
     * Upserts on the GeoNames id, so reloading a newer dump refreshes in
     * place. Returns how many places were written.
     *
     * @param string $cities_path      cities500.txt (tab-separated, one place per line)
     * @param string $admin1_path      admin1CodesASCII.txt ("CC.CODE\tname\t…")
     * @param string $country_info_path countryInfo.txt ("#"-commented, ISO code first, name fifth)
     */
    public static function import(string $cities_path, string $admin1_path, string $country_info_path): int
    {
        $regions = [];

        foreach (self::rowsOf($admin1_path) as $fields) {
            if (isset($fields[1])) {
                $regions[$fields[0]] = $fields[1];
            }
        }

        $countries = [];

        foreach (self::rowsOf($country_info_path) as $fields) {
            if (isset($fields[4])) {
                $countries[$fields[0]] = $fields[4];
            }
        }

        $written = 0;
        $batch = [];

        foreach (self::rowsOf($cities_path) as $fields) {
            // geonameid, name, [2..3 skipped], latitude, longitude,
            // [6..7 skipped], country code, [9 skipped], admin1 code, …,
            // population at 14.
            if (!isset($fields[14])) {
                continue;
            }

            $batch[] = [
                (int) $fields[0],
                mb_substr($fields[1], 0, 200),
                mb_substr($regions[$fields[8] . '.' . $fields[10]] ?? '', 0, 100),
                mb_substr($countries[$fields[8]] ?? '', 0, 100),
                (float) $fields[4],
                (float) $fields[5],
                max(0, (int) $fields[14]),
            ];

            if (count($batch) === 500) {
                $written += self::writeBatch($batch);
                $batch = [];
            }
        }

        if ($batch !== []) {
            $written += self::writeBatch($batch);
        }

        return $written;
    }

    /** @return \Generator<string[]> each data line of a GeoNames TSV, split */
    private static function rowsOf(string $path): \Generator
    {
        $handle = fopen($path, 'r');

        if ($handle === false) {
            throw new \RuntimeException('Could not open ' . $path . '.');
        }

        try {
            while (($line = fgets($handle)) !== false) {
                $line = rtrim($line, "\r\n");

                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }

                yield explode("\t", $line);
            }
        } finally {
            fclose($handle);
        }
    }

    /** @param array<int, array{int, string, string, string, float, float, int}> $batch */
    private static function writeBatch(array $batch): int
    {
        $placeholders = implode(', ', array_fill(0, count($batch), '(?, ?, ?, ?, ?, ?, ?)'));
        $parameters = array_merge(...$batch);

        DB::run('
INSERT INTO `Places` (`placeId`, `title`, `region`, `country`, `latitude`, `longitude`, `population`)
    VALUES ' . $placeholders . '
    ON DUPLICATE KEY UPDATE `title` = VALUES(`title`), `region` = VALUES(`region`), `country` = VALUES(`country`),
        `latitude` = VALUES(`latitude`), `longitude` = VALUES(`longitude`), `population` = VALUES(`population`)
', str_repeat('isssddi', count($batch)), ...$parameters);

        return count($batch);
    }
}
