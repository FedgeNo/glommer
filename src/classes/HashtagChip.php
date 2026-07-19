<?php

declare(strict_types=1);

/**
 * A clickable "#tag" chip linking to the tag's page, optionally with the number
 * of posts carrying it. The tags on the /tags/ Trending cloud, read from the
 * TrendingHashtags table (see TrendingHashtagList) -> DB::rows().
 */
class HashtagChip extends Anchor
{
    public ?string $class = 'HashtagChip';

    public ?string $slug = null;
    public ?string $title = null;
    public ?int $postCount = null;

    public function toDOM(): \DOMElement
    {
        $this -> href = ServerURL::absolute('/tags/' . $this -> slug);
        $this -> contents[] = '#' . $this -> title;

        if ($this -> postCount !== null) {
            $count_span = new Span();
            $count_span -> class = 'HashtagChipCount';
            $count_span -> contents[] = (string) $this -> postCount;
            $this -> contents[] = $count_span;
        }

        return parent::toDOM();
    }
}
