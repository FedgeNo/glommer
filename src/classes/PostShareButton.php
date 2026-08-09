<?php

declare(strict_types=1);

/**
 * Shares a post's permalink - the Web Share sheet where the browser has one,
 * otherwise a copy to the clipboard. Shown to everyone, signed in or not, which
 * is why it leads the action bar.
 */
class PostShareButton extends ButtonButton
{
    public const GLYPH = '📤';

    public function __construct(string $post_url)
    {
        parent::__construct();

        $this -> attributes['data-share-url'] = $post_url;
        $this -> nameIt('Share');
        $this -> contents[] = self::GLYPH;
    }
}
