<?php

declare(strict_types=1);

/**
 * The client-rendered map on /map. A bare container carrying the tile source as
 * data attributes; PostMap.js (loaded on .PostMap) initialises Leaflet, fetches
 * the geotagged posts from /api/map-posts, and clusters them. The server only
 * ships this empty container - there is no post data in the initial markup.
 */
class PostMap extends Div
{
    public ?string $class = 'PostMap';
    public array $mixins = ['Card'];

    // Where to open, when the map was linked to with a point (a post's place
    // line, say). Null opens on the whole world.
    public ?float $latitude = null;
    public ?float $longitude = null;

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-tile-url'] = MapTiles::url();
        $this -> attributes['data-tile-attribution'] = MapTiles::attribution();

        if ($this -> latitude !== null && $this -> longitude !== null) {
            $this -> attributes['data-center-latitude'] = (string) $this -> latitude;
            $this -> attributes['data-center-longitude'] = (string) $this -> longitude;
        }

        return parent::toDOM();
    }
}
