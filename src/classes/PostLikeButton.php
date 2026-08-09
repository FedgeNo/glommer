<?php

declare(strict_types=1);

/**
 * The like toggle on a post's action bar. Carries the current state as
 * data-liked, which is what Post.js reads before it flips it.
 */
class PostLikeButton extends ToggleButton
{
    public function __construct(bool $liked, int $count)
    {
        parent::__construct();

        $this -> attributes['data-liked'] = $liked ? '1' : '0';

        // Pressing it now would take the like away, and every button in that
        // position wears the same state.
        if ($liked) {
            $this -> class .= ' Removing';
        }

        // One live label, rewritten in place as the count moves, inside a
        // width reserved for the longest form it can take.
        $this -> labels = [self::label($liked, $count)];
        $this -> reserve = self::label(true, 0) . ' ' . self::RESERVED_COUNT;
    }

    /**
     * The count only appears once there is one, so a post nobody has liked
     * reads "Like" rather than "Like (0)". Post.js builds the same label after
     * a click, and the two have to agree or the button changes wording when it
     * is pressed.
     */
    public static function label(bool $liked, int $count): string
    {
        $label = $liked ? 'Unlike' : 'Like';

        if ($count) {
            $label .= ' (' . $count . ')';
        }

        return $label;
    }
}
