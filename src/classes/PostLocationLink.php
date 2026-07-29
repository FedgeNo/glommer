<?php

declare(strict_types=1);

/**
 * The place line under a post's timestamp: a link to the map, opened on where
 * the post was filed. Seeing the spot answers "where is that?" better than a
 * list does, and the map's own pin menu carries on to the nearby feed from
 * there. Coordinates rather than a place name - there is no geocoder here, and
 * inventing "Vancouver" from a point would be a guess.
 */
class PostLocationLink extends Anchor
{
    public ?string $class = 'PostLocationLink';

    public function __construct(float $latitude, float $longitude)
    {
        parent::__construct(
            ServerURL::absolute('/map?lat=' . rawurlencode((string) $latitude) . '&lng=' . rawurlencode((string) $longitude)),
            self::label($latitude, $longitude)
        );

        $this -> attributes['title'] = 'Show this place on the map';
    }

    /**
     * Trimmed to four decimals - about eleven metres, enough to place a post
     * without printing a wall of digits. The link itself carries the exact
     * position, so nothing is lost from what the feed centres on.
     */
    private static function label(float $latitude, float $longitude): string
    {
        return number_format($latitude, 4) . ', ' . number_format($longitude, 4);
    }
}
