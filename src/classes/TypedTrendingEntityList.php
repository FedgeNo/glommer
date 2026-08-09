<?php

declare(strict_types=1);

/**
 * The trending topics of one kind - what /topics/{type}/ lists.
 *
 * Narrows TrendingEntityList rather than standing beside it: same chips, same
 * ordering, same page size, one clause different.
 */
class TypedTrendingEntityList extends TrendingEntityList
{
    public ?string $type = null;

    protected function rows(): array
    {
        return Trending::ofType((string) $this -> type, static::PAGE_SIZE);
    }
}
