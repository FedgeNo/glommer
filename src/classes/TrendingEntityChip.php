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
    public int $popularity = 0;

    /**
     * Which figure goes beside the name. postCount is this window's, which is
     * what a trending chip is about; a chip in the standing list is not, and a
     * window count on a topic that last trended in March describes nothing.
     */
    public bool $countsAllTime = false;

    /** Where this topic lives: /topics/{type}/{slug}. */
    public function url(): string
    {
        return ServerURL::absolute(
            '/topics/' . rawurlencode(EntityType::slug((string) $this -> type)) . '/' . rawurlencode((string) $this -> slug)
        );
    }

    /** What the client twin rebuilds this from. @return array<string, mixed> */
    public function payload(): array
    {
        return [
            'entityId' => (int) $this -> entityId,
            'type' => (string) $this -> type,
            'title' => (string) $this -> title,
            'url' => $this -> url(),
            'count' => $this -> count(),
            'canModerate' => Auth::canModerate(),
            'banLabel' => (string) (Strings::for(TrendingEntityBanButton::class)['name'] ?? ''),
        ];
    }

    private function count(): ?int
    {
        return $this -> countsAllTime ? $this -> popularity : $this -> postCount;
    }

    public function toDOM(): \DOMElement
    {
        $link = new Anchor($this -> url(), $this -> title);
        $link -> class = 'TrendingEntityLink';

        if ($this -> count() !== null) {
            $count_span = new TrendingEntityCount();
            $count_span -> addContent((string) $this -> count());
            $link -> addContent($count_span);
        }

        $this -> addContent($link);

        if (Auth::canModerate()) {
            $this -> addContent(new TrendingEntityBanButton((string) $this -> type, (string) $this -> title));
        }

        return parent::toDOM();
    }
}
