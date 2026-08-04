<?php

declare(strict_types=1);

/**
 * Passes a post on to your own friends and Fediverse followers, or takes that
 * back. One button in two states, since it is one decision.
 */
class PostRepostButton extends ButtonButton
{
    public function __construct(bool $reposted, int $count)
    {
        parent::__construct();

        if ($reposted) {
            $this -> class .= ' Removing';
        }

        $this -> contents[] = self::label($reposted, $count);
    }

    /** PostRepostButton.js builds the same label after a click. */
    public static function label(bool $reposted, int $count): string
    {
        $label = $reposted ? 'Unrepost' : 'Repost';

        if ($count) {
            $label .= ' (' . $count . ')';
        }

        return $label;
    }
}
