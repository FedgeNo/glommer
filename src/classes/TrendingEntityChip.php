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
 *
 * Fetched directly off TrendingEntities via Trending::current() -> DB::rows().
 */
class TrendingEntityChip extends Div
{
    public ?string $class = 'TrendingEntityChip d-flex align-items-center gap-1';

    public ?int $entityId = null;
    public ?string $type = null;
    public ?string $title = null;
    public float $score = 0.0;
    public ?int $postCount = null;
    public int $userCount = 0;

    public function toDOM(): \DOMElement
    {
        $link = new Anchor(ServerURL::absolute('/search?q=' . urlencode((string) $this -> title)), $this -> title);
        $link -> class = 'TrendingEntityLink';

        if ($this -> postCount !== null) {
            $count_span = new Span();
            $count_span -> class = 'TrendingEntityCount';
            $count_span -> addContent((string) $this -> postCount);
            $link -> addContent($count_span);
        }

        $this -> addContent($link);

        if (Auth::canModerate()) {
            $this -> addContent(new BanTrendingEntityButton((string) $this -> type, (string) $this -> title));
        }

        return parent::toDOM();
    }
}
