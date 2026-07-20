<?php

declare(strict_types=1);

/**
 * The /tags/ "Popular" section. Server-rendered as a plain <ul> of tag links -
 * a readable, crawlable list that works with no JS and stays a scrollable list
 * on narrow screens - which tag-graph.js upgrades in place to a 3D
 * force-directed graph above the layout breakpoint (tags that share more posts
 * spring together, drag to rotate). It stays a list below the breakpoint
 * because the graph captures touch and wheel to rotate/zoom, which would trap
 * the page's scroll on a phone.
 */
class HashtagGraphSection extends ListSection
{
    public ?string $class = 'HashtagGraphSection';

    protected string $heading = 'Popular';

    protected function list(): ItemLoader
    {
        return new HashtagGraphList();
    }
}
