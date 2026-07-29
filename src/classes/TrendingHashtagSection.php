<?php

declare(strict_types=1);

/**
 * The /tags/ "Trending" cloud.
 */
class TrendingHashtagSection extends ListSection
{
    public ?string $class = 'TrendingHashtagSection';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    protected string $heading = 'Trending';

    protected function list(): ItemLoader
    {
        return new TrendingHashtagList();
    }
}
