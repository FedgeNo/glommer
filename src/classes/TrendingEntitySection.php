<?php

declare(strict_types=1);

/**
 * The "Trending" section on the /trending-topics page: extracted entities, not
 * hashtags - EntityExtractor only produces 'hashtag'-type entities today, but
 * this component (and everything under it - TrendingEntityChip, Trending.php
 * itself) has no hashtag-specific naming, styling, or behavior anywhere.
 * Separate from the /tags/ tag clouds (HashtagGraphSection /
 * TrendingHashtagSection / HashtagChip, which genuinely ARE hashtag-only and
 * unrelated to the trending engine) rather than sharing a component with them.
 */
class TrendingEntitySection extends ListSection
{
    public ?string $class = 'TrendingEntitySection d-flex flex-column gap-2';

    protected string $heading = 'Trending';

    protected function list(): ItemLoader
    {
        return new TrendingEntityList();
    }
}
