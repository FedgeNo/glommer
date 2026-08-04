<?php

declare(strict_types=1);

/**
 * The bookmark toggle on a post's action bar. Carries the current state as
 * data-bookmarked, which is what Post.js reads before it flips it.
 */
class PostBookmarkButton extends ButtonButton
{
    public function __construct(bool $bookmarked)
    {
        parent::__construct();

        $this -> attributes['data-bookmarked'] = $bookmarked ? '1' : '0';

        if ($bookmarked) {
            $this -> class .= ' Removing';
        }

        $this -> contents[] = self::label($bookmarked);
    }

    /** Post.js builds the same label after a click. */
    public static function label(bool $bookmarked): string
    {
        return $bookmarked ? 'Bookmarked' : 'Bookmark';
    }
}
