<?php

declare(strict_types=1);

/**
 * The like toggle on a post's action bar. Carries the current state as
 * data-liked, which is what HTMLObjects.js's Post reads before it flips it.
 *
 * A thumb rather than the word, since this is the most-pressed thing on the
 * page and a row of nine worded buttons was bigger than the posts it sat
 * under - and the reader's own thumb, in whatever skin tone they picked in the
 * emoji picker. Having asked somebody which hand is theirs, it would be a
 * strange thing to then show them a different one.
 */
class PostLikeButton extends ButtonButton
{
    public const GLYPH = '👍';

    public function __construct(bool $liked, int $count)
    {
        parent::__construct();

        $tone = SkinTone::forViewer();

        $this -> attributes['data-liked'] = $liked ? '1' : '0';
        $words = Strings::for(self::class);
        $this -> nameIt((string) ($words[$liked ? 'unlike' : 'like'] ?? ''));
        $this -> pressed($liked);

        // Pressing it now would take the like away, and every button in that
        // position wears the same state.
        if ($liked) {
            $this -> class .= ' Removing';
        }

        // Sized by what it says. The count moving is a digit's worth of
        // width on a row anchored at its start, so nothing else shifts.
        $this -> contents[] = self::label($liked, $count, $tone);
    }

    /**
     * The count only appears once there is one, so a post nobody has liked is
     * the thumb alone. HTMLObjects.js's Post builds the same label after a click, and the
     * two have to agree or the button changes shape when it is pressed.
     *
     * The thumb is the same either way - a thumbs-up has no emptied form the
     * way a heart does - so what says you have liked it is the colour, and
     * aria-pressed for anybody not reading colour.
     */
    public static function label(bool $liked, int $count, ?string $tone = null): string
    {
        $thumb = SkinTone::applied(self::GLYPH, $tone);

        return $count > 0 ? $thumb . ' ' . $count : $thumb;
    }
}
