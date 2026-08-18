<?php

declare(strict_types=1);

/**
 * What is trending, of every kind, on /topics/ - hashtags alongside the people,
 * organizations and places the extractor found in post text. This component and
 * everything under it has no hashtag-specific naming, styling or behaviour
 * anywhere.
 *
 * Separate from the /tags/ tag clouds (HashtagGraphList / TrendingHashtagList /
 * HashtagChip, which genuinely ARE hashtag-only and unrelated to the trending
 * engine) rather than sharing a component with them.
 */
class TrendingEntityList extends ItemList
{
    public ?string $class = 'TrendingEntityList';
    public ?string $type = null;

    /**
     * This list shows this many and stops: what is trending is a top, and a
     * hundredth-place spike is not one. PopularEntityList inherits the size and
     * does page, since it is a standing list rather than a top.
     */
    public const PAGE_SIZE = 50;

    protected function rows(): array
    {
        EntityRanker::refreshIfStale();

        if ($this -> type !== null) {
            return DB::rows('
SELECT *
    FROM `Entities`
    WHERE `type` = ? AND `computedAt` = ?
    ORDER BY `score` DESC
    LIMIT ?
', Entity::class, 'ssi', $this -> type, EntityRanker::lastRun(), static::PAGE_SIZE);
        }

        return DB::rows('
SELECT *
    FROM `Entities`
    WHERE `computedAt` = ?
    ORDER BY `score` DESC
    LIMIT ?
', Entity::class, 'si', EntityRanker::lastRun(), static::PAGE_SIZE);
    }
}
