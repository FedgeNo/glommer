<?php

declare(strict_types=1);

/**
 * The /nearby heading over the posts closest to the given point - named from
 * the gazetteer when somewhere is close enough to name ("Near Town, Region,
 * Country"), plain "Nearby" when nowhere is (the open ocean, or a place
 * directory that hasn't loaded).
 */
class NearbyFeedSection extends ListSection
{
    public ?string $class = 'NearbyFeedSection';

    protected string $heading = 'Nearby';

    public ?float $latitude = null;
    public ?float $longitude = null;
    public int $offset = 0;

    public function toDOM(): \DOMElement
    {
        if ($this -> latitude !== null && $this -> longitude !== null) {
            $place = Place::nearest($this -> latitude, $this -> longitude);

            if ($place !== null) {
                $this -> heading = 'Near ' . $place -> label();
            }
        }

        return parent::toDOM();
    }

    protected function list(): ItemLoader
    {
        return new NearbyFeedList([
            'latitude' => $this -> latitude,
            'longitude' => $this -> longitude,
            'offset' => $this -> offset,
        ]);
    }
}
