<?php

declare(strict_types=1);

/**
 * A trending topic's chip - deliberately type-agnostic (no hashtag-specific
 * display like a leading '#', no borrowing HashtagChip's class): the whole
 * point of the trending pipeline is that any kind of topic flows through the
 * same scoring, storage and display without this class needing to know which
 * one it is showing. A moderator viewing it also gets a Ban control alongside.
 *
 * Fetched directly off TrendingEntities via Trending::current() -> DB::rows().
 */
class TrendingEntityChip extends Div
{
    public ?string $class = 'TrendingEntityChip';
    public array $mixins = ['d-flex', 'align-items-center', 'gap-1'];

    public ?int $entityId = null;
    public ?string $type = null;
    public ?string $slug = null;
    public ?string $title = null;
    public float $score = 0.0;
    public ?int $postCount = null;
    public int $userCount = 0;

    /** Where this topic lives: /topics/{type}/{slug}. */
    public function url(): string
    {
        return ServerURL::absolute(
            '/topics/' . rawurlencode((string) $this -> type) . '/' . rawurlencode((string) $this -> slug)
        );
    }

    public function toDOM(): \DOMElement
    {
        $link = new Anchor($this -> url(), $this -> title);
        $link -> class = 'TrendingEntityLink';

        if ($this -> postCount !== null) {
            $count_span = new TrendingEntityCount();
            $count_span -> addContent((string) $this -> postCount);
            $link -> addContent($count_span);
        }

        $this -> addContent($link);

        if (Auth::canModerate()) {
            $this -> addContent(new TrendingEntityBanButton((string) $this -> type, (string) $this -> title));
        }

        return parent::toDOM();
    }
}
