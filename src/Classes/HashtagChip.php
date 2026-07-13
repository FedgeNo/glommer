<?php

declare(strict_types=1);

/**
 * A clickable "#tag" chip linking to the tag's page, optionally with the number
 * of posts carrying it. Used across the /tags/ directory (popular and trending).
 */
class HashtagChip extends Anchor
{
    public ?string $class = 'HashtagChip';

    public function __construct(string $tag, ?int $count = null)
    {
        parent::__construct(ServerURL::absolute('/tags/' . $tag));

        $this -> contents[] = '#' . $tag;

        if ($count !== null) {
            $count_span = new Span();
            $count_span -> class = 'HashtagChipCount';
            $count_span -> contents[] = (string) $count;
            $this -> contents[] = $count_span;
        }
    }
}
