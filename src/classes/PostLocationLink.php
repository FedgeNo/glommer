<?php

declare(strict_types=1);

/**
 * The place line under a post's timestamp: a link to the map, opened on where
 * the post was filed. Seeing the spot answers "where is that?" better than a
 * list does, and the map's own pin menu carries on to the nearby feed from
 * there.
 *
 * Named from this server's own gazetteer (the Places table) when somewhere is
 * close enough to name honestly; coordinates otherwise - a point in the ocean
 * or a table not yet loaded gets the truth, not the nearest coast.
 */
class PostLocationLink extends Anchor
{
    public ?string $class = 'PostLocationLink';

    public float $latitude;
    public float $longitude;
    public ?string $placeLabel = null;

    // The point itself is kept rather than only the link built from it, so
    // anything else this element grows to need - a data attribute, a second
    // link shape - still has the position to work from.
    public function __construct(float $latitude, float $longitude, ?string $place_label = null)
    {
        parent::__construct();

        $this -> latitude = $latitude;
        $this -> longitude = $longitude;
        $this -> placeLabel = $place_label;
    }

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);

        $this -> href = ServerURL::absolute(
            '/map?lat=' . (string) $this -> latitude . '&lng=' . (string) $this -> longitude
        );

        $this -> attributes['title'] = (string) ($words['title'] ?? '');

        $this -> contents[] = $this -> placeLabel !== null && $this -> placeLabel !== ''
            ? $this -> placeLabel
            : $this -> coordinates((string) ($words['between'] ?? ''));

        return parent::toDOM();
    }

    /**
     * Trimmed to four decimals - about eleven metres, enough to place a post
     * without printing a wall of digits. The link itself carries the exact
     * position, so nothing is lost from what the feed centres on.
     */
    private function coordinates(string $between): string
    {
        return number_format($this -> latitude, 4) . $between . number_format($this -> longitude, 4);
    }
}
