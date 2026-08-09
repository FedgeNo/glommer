<?php

declare(strict_types=1);

/**
 * Passes a post on to your own friends and Fediverse followers, or takes that
 * back. One button in two states, since it is one decision.
 *
 * No second glyph for the two states - the arrows have no natural opposite the
 * way a heart does - so what says it is on is the colour, and aria-pressed for
 * anybody not reading colour.
 */
class PostRepostButton extends ButtonButton
{
    private const GLYPH = '🔁';

    public function __construct(bool $reposted, int $count)
    {
        parent::__construct();

        $this -> nameIt($reposted ? 'Undo repost' : 'Repost');
        $this -> pressed($reposted);

        if ($reposted) {
            $this -> class .= ' Removing';
        }

        $this -> contents[] = self::label($reposted, $count);
    }

    /** PostRepostButton.js builds the same label after a click. */
    public static function label(bool $reposted, int $count): string
    {
        return $count > 0 ? self::GLYPH . ' ' . $count : self::GLYPH;
    }
}
