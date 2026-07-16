<?php

declare(strict_types=1);

/**
 * The "Trending" section on the /trending-topics page: extracted entities, not
 * hashtags - EntityExtractor only produces 'hashtag'-type entities today,
 * but this component (and everything under it - TrendingEntityChip,
 * Trending.php itself) has no hashtag-specific naming, styling, or behavior
 * anywhere. Deliberately separate from HashtagCloud/HashtagChip (the
 * "Popular" section, which genuinely IS hashtag-only and unrelated to the
 * trending engine) rather than sharing a component with it.
 */
class TrendingSection extends Div
{
    public ?string $class = 'TrendingSection d-flex flex-column gap-2';

    /**
     * @param array<int, array{entityId: int, entityType: string, entityValue: string, score: float, postCount: int, userCount: int}> $entities
     */
    public function __construct(array $entities)
    {
        parent::__construct();

        $title = new Heading2();
        $title -> addContent('Trending');
        $this -> addContent($title);

        $list = new Div();
        $list -> class = 'TrendingEntities d-flex flex-wrap gap-2';

        foreach ($entities as $entity) {
            $list -> addContent(new TrendingEntityChip($entity['entityType'], $entity['entityValue'], $entity['postCount']));
        }

        $this -> addContent($list);
    }
}
