<?php

declare(strict_types=1);

/**
 * A latitude/longitude pair parsed from request input. Both coordinates must be
 * present and in range or there is no point - a post filed at "somewhere on the
 * equator" because one half failed to parse would be worse than no location at
 * all, so it is always both or neither.
 */
class Coordinates
{
    public function __construct(public readonly float $latitude, public readonly float $longitude)
    {
    }

    /** The pair, or null if either side is missing or out of range. */
    public static function parse(mixed $latitude, mixed $longitude): ?self
    {
        $latitude = is_string($latitude) ? trim($latitude) : $latitude;
        $longitude = is_string($longitude) ? trim($longitude) : $longitude;

        if (!is_numeric($latitude) || !is_numeric($longitude)) {
            return null;
        }

        $latitude = (float) $latitude;
        $longitude = (float) $longitude;

        if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
            return null;
        }

        return new self($latitude, $longitude);
    }
}
