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
    public ?string $class = 'Card PostMap';

    public function toDOM(): \DOMElement
    {
        $this -> attributes['data-tile-url'] = MapTiles::url();
        $this -> attributes['data-tile-attribution'] = MapTiles::attribution();

        return parent::toDOM();
    }
}
