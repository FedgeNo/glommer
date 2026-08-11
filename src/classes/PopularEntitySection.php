<?php

declare(strict_types=1);

/**
 * The topics of one kind, under a heading naming that kind.
 *
 * The type has to arrive as a constructor property rather than being set
 * afterwards: a section builds its list while it is being constructed, so a
 * type assigned later would arrive after the list had already loaded without
 * one.
 */
class PopularEntitySection extends TrendingEntitySection
{
    public ?string $class = 'PopularEntitySection';

    public ?string $type = null;

    public function __construct(array|object|null $properties = null)
    {
        parent::__construct($properties);

        $this -> heading = EntityType::plural((string) $this -> type);
    }

    protected function list(): ItemLoader
    {
        return new PopularEntityList(['type' => $this -> type]);
    }
}
