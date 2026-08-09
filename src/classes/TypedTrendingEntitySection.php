<?php

declare(strict_types=1);

/**
 * The trending topics of one kind, under a heading naming that kind.
 *
 * The type has to arrive as a constructor property rather than being set
 * afterwards: a section builds its list while it is being constructed, so a
 * type assigned later would arrive after the list had already loaded without
 * one.
 */
class TypedTrendingEntitySection extends TrendingEntitySection
{
    public ?string $type = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> heading = EntityType::plural((string) $this -> type);
    }

    protected function list(): ItemLoader
    {
        return new TypedTrendingEntityList(['type' => $this -> type]);
    }
}
