<?php

declare(strict_types=1);

/**
 * The /nearby heading over the posts closest to the given point.
 */
class NearbyFeedSection extends ListSection
{
    public ?string $class = 'NearbyFeedSection';

    protected string $heading = 'Nearby';

    public ?float $latitude = null;
    public ?float $longitude = null;
    public int $offset = 0;

    protected function list(): ItemLoader
    {
        return new NearbyFeedList([
            'latitude' => $this -> latitude,
            'longitude' => $this -> longitude,
            'offset' => $this -> offset,
        ]);
    }
}
