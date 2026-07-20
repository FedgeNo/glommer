<?php

declare(strict_types=1);

/**
 * The "Trending" section on the /trending-topics page: extracted entities, not
 * hashtags - EntityExtractor only produces 'hashtag'-type entities today, but
 * this component (and everything under it - TrendingEntityChip, Trending.php
 * itself) has no hashtag-specific naming, styling, or behavior anywhere.
 * Deliberately separate from the /tags/ tag clouds (HashtagGraph /
 * TrendingHashtagList / HashtagChip, which genuinely ARE hashtag-only and
 * unrelated to the trending engine) rather than sharing a component with them.
 */
class TrendingSection extends ListSection
{
    public ?string $class = 'TrendingSection d-flex flex-column gap-2';

    protected string $heading = 'Trending';

    protected string $itemsClass = 'TrendingEntities d-flex flex-wrap gap-2';

    /** The section shows this many entities and stops - there is no next page. */
    public const PAGE_SIZE = 50;

    protected function rows(): array
    {
        return Trending::current(static::PAGE_SIZE);
    }
}
