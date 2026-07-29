<?php

declare(strict_types=1);

/**
 * The place line under a post's timestamp: a link into the nearby feed centred
 * on where the post was filed. Coordinates rather than a place name - there is
 * no geocoder here, and inventing "Vancouver" from a point would be a guess -
 * so it shows the position and lets the feed it links to do the talking.
 */
class PostLocationLink extends Anchor
{
    public ?string $class = 'PostLocationLink';

    public function __construct(float $latitude, float $longitude)
    {
        parent::__construct(
            ServerURL::absolute('/nearby?lat=' . rawurlencode((string) $latitude) . '&lng=' . rawurlencode((string) $longitude)),
            self::label($latitude, $longitude)
        );

        $this -> attributes['title'] = 'See posts near here';
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
