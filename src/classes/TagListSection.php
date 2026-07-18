<?php

declare(strict_types=1);

/**
 * A titled list of HashtagChips - one "cloud" of tags on the /tags/ directory
 * (e.g. the "Trending" tags). Built from Hashtag::trending()/popular() rows.
 */
class TagListSection extends ListSection
{
    public ?string $class = 'TagListSection d-flex flex-column gap-2';

    protected string $itemsClass = 'TagItems d-flex flex-wrap gap-2';

    /**
     * @param HashtagChip[] $tags
     */
    public function __construct(string $heading, array $tags)
    {
        parent::__construct();

        $this -> heading = $heading;
        $this -> items = $tags;
    }
}
