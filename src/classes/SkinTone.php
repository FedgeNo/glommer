<?php

declare(strict_types=1);

/**
 * The reader's chosen skin tone, applied to an emoji this site shows them.
 *
 * The tone is picked once in the emoji picker and kept on the account
 * (Users.skinTone), on the picker's own 0-to-5 scale where 0 is the default
 * yellow. Anywhere the site puts a hand in front of somebody - the thumb on
 * the like button - it should be the hand they chose, not the one this code
 * happened to type.
 *
 * The mirror of scripts/SkinTone.js, since a card can be rendered by either
 * side and the same thumb has to come out of both.
 */
class SkinTone
{
    /**
     * The Fitzpatrick modifiers, by the scale the picker reports. Written as
     * code points rather than as escapes, so the file carries no character
     * nobody can see.
     */
    private const MODIFIERS = [
        1 => 0x1F3FB,
        2 => 0x1F3FC,
        3 => 0x1F3FD,
        4 => 0x1F3FE,
        5 => 0x1F3FF,
    ];

    /** Turns an emoji "text" presentation into its "emoji" one. */
    private const VARIATION_SELECTOR = 0xFE0F;

    /**
     * The emoji as this reader should see it. Unchanged where they have chosen
     * nothing, chosen the default, or the emoji is not one that takes a tone.
     */
    public static function applied(string $emoji, ?string $tone): string
    {
        $index = (int) $tone;

        if (!isset(self::MODIFIERS[$index])) {
            return $emoji;
        }

        // A modifier replaces the variation selector rather than following it:
        // the selector asks for the coloured form, and a modifier already
        // means that. Left in place it renders as the emoji then a stray
        // swatch.
        $selector = mb_chr(self::VARIATION_SELECTOR, 'UTF-8');

        if (str_ends_with($emoji, $selector)) {
            $emoji = mb_substr($emoji, 0, mb_strlen($emoji) - 1);
        }

        return $emoji . mb_chr(self::MODIFIERS[$index], 'UTF-8');
    }

    /** What the signed-in reader has chosen, if anybody is reading. */
    public static function forViewer(): ?string
    {
        return Auth::user() ?-> skinTone;
    }
}
