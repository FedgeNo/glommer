<?php

declare(strict_types=1);

/**
 * The bookmark toggle on a post's action bar. Carries the current state as
 * data-bookmarked, which is what Post.js reads before it flips it.
 *
 * One glyph in both states - a bookmark has no emptied form the way a heart
 * does - so the colour says whether it is on, and aria-pressed says so to
 * anybody not reading the colour.
 */
class PostBookmarkButton extends ButtonButton
{
    private const GLYPH = '🔖';

    public function __construct(bool $bookmarked)
    {
        parent::__construct();

        $this -> attributes['data-bookmarked'] = $bookmarked ? '1' : '0';
        $this -> nameIt(self::label($bookmarked));
        $this -> pressed($bookmarked);

        if ($bookmarked) {
            $this -> class .= ' Removing';
        }

        $this -> contents[] = self::GLYPH;
    }

    /** The name it goes by, which is the only part that changes. */
    public static function label(bool $bookmarked): string
    {
        return $bookmarked ? 'Remove bookmark' : 'Bookmark';
    }
}
