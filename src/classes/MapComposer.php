<?php

declare(strict_types=1);

/**
 * The composer below the map, for posting to a place rather than to wherever
 * you happen to be standing. Hidden until the viewer clicks a point on the map;
 * PostMap.js then reveals it and fills in the clicked coordinates, so the post
 * is filed at the spot on screen instead of the browser's own geolocation.
 *
 * A PostComposer subclass purely so it renders its own class name to hide and
 * reveal against - the class chain still carries PostComposer, so Composer.js
 * mounts it like any other and needs no map-specific case.
 */
class MapComposer extends PostComposer
{
}
