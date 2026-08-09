<?php

declare(strict_types=1);

/**
 * The "Trending" section on /topics/ - everything trending, of every kind.
 *
 * Separate from the /tags/ tag clouds (HashtagGraphSection /
 * TrendingHashtagSection / HashtagChip, which genuinely ARE hashtag-only and
 * unrelated to the trending engine) rather than sharing a component with them.
 */
class TrendingEntitySection extends ListSection
{
    public ?string $class = 'TrendingEntitySection';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    protected string $heading = 'Trending';

    protected function list(): ItemLoader
    {
        return new TrendingEntityList();
    }
}
