<?php

declare(strict_types=1);

/**
 * A titled section of HashtagChips - one "cloud" on the /tags/ directory (the
 * "Popular" section). Built from Hashtag::popular() rows.
 */
class HashtagCloud extends Div
{
    public ?string $class = 'HashtagCloud d-flex flex-column gap-2';

    /**
     * @param HashtagChip[] $tags
     */
    public function __construct(string $heading, array $tags)
    {
        parent::__construct();

        $title = new Heading2();
        $title -> contents[] = $heading;
        $this -> contents[] = $title;

        $chips = new Div();
        $chips -> class = 'HashtagChips d-flex flex-wrap gap-2';
        $chips -> addContents($tags);

        $this -> contents[] = $chips;
    }
}
