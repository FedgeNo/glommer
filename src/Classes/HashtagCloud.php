<?php

declare(strict_types=1);

/**
 * A titled section of HashtagChips - one "cloud" on the /tags/ directory (e.g.
 * "Trending" or "Popular"). Built from Hashtag::popular()/trending() rows.
 */
class HashtagCloud extends Div
{
    public ?string $class = 'HashtagCloud d-flex flex-column gap-2';

    /**
     * @param array<int, array{tag: string, postCount: int}> $tags
     */
    public function __construct(string $heading, array $tags)
    {
        parent::__construct();

        $title = new Heading2();
        $title -> contents[] = $heading;
        $this -> contents[] = $title;

        $chips = new Div();
        $chips -> class = 'HashtagChips d-flex flex-wrap gap-2';

        foreach ($tags as $tag) {
            $chips -> addContent(new HashtagChip($tag['tag'], $tag['postCount']));
        }

        $this -> contents[] = $chips;
    }
}
