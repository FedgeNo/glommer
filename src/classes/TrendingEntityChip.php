<?php

declare(strict_types=1);

/**
 * A trending entity's chip - deliberately entity-type-agnostic (no hashtag-
 * specific display like a leading '#', no borrowing HashtagChip's class):
 * EntityExtractor only produces 'hashtag' entities today, but the whole point
 * of the trending pipeline is that any entity type can flow through the same
 * scoring/storage/display without this class needing to know or care which
 * one it's showing. Links to /search results for it rather than a type-
 * specific page, since not every entity type will have one of those the way
 * hashtags have /tags/{tag}. A moderator viewing it also gets a Ban control
 * alongside.
 */
class TrendingEntityChip extends Div
{
    public ?string $class = 'TrendingEntityChip d-flex align-items-center gap-1';

    public function __construct(string $entity_type, string $entity_value, ?int $count = null)
    {
        parent::__construct();

        $link = new Anchor(ServerURL::absolute('/search?q=' . urlencode($entity_value)), $entity_value);
        $link -> class = 'TrendingEntityLink';

        if ($count !== null) {
            $count_span = new Span();
            $count_span -> class = 'TrendingEntityCount';
            $count_span -> addContent((string) $count);
            $link -> addContent($count_span);
        }

        $this -> addContent($link);

        if (Auth::canModerate()) {
            $this -> addContent(new BanTrendingEntityButton($entity_type, $entity_value));
        }
    }
}
