<?php

declare(strict_types=1);

/**
 * The global feed as the RSS reader sees it - the same selection the site
 * shows, so the two can't drift into disagreeing about what's public, over a
 * longer page than a screenful.
 */
class RSSFeedList extends GlobalFeedList
{
    public const PAGE_SIZE = 50;
}
