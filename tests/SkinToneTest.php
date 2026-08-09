<?php

declare(strict_types=1);

/**
 * The reader's chosen skin tone, applied to the emoji this site shows them.
 *
 * Having asked somebody in the emoji picker which hand is theirs, showing them
 * a different one on the like button is a small rudeness the software can
 * easily avoid.
 */
class SkinToneTest extends TestCase
{
    private const THUMB = '👍';

    public function testAToneIsAppliedToTheGlyph(): void
    {
        $medium = SkinTone::applied(self::THUMB, '3');

        $this -> assertFalse($medium === self::THUMB, 'the thumb changed');
        $this -> assertTrue(str_starts_with($medium, self::THUMB), 'and it is still a thumb');
        $this -> assertSame(2, mb_strlen($medium), 'the glyph plus one modifier');
    }

    /** Each of the five is a different hand. */
    public function testEveryToneIsItsOwn(): void
    {
        $thumbs = [];

        foreach (['1', '2', '3', '4', '5'] as $tone) {
            $thumbs[] = SkinTone::applied(self::THUMB, $tone);
        }

        $this -> assertSame(5, count(array_unique($thumbs)));
    }

    /** Nobody has said, or they chose the default: the glyph is left alone. */
    public function testNoChoiceLeavesTheGlyphAsItIs(): void
    {
        $this -> assertSame(self::THUMB, SkinTone::applied(self::THUMB, null));
        $this -> assertSame(self::THUMB, SkinTone::applied(self::THUMB, '0'));
        $this -> assertSame(self::THUMB, SkinTone::applied(self::THUMB, ''));
        $this -> assertSame(self::THUMB, SkinTone::applied(self::THUMB, 'nonsense'));
    }

    /**
     * A modifier replaces the variation selector rather than following it.
     * Left in place, the pair renders as the emoji and then a stray swatch.
     */
    public function testAVariationSelectorGivesWayToTheModifier(): void
    {
        $writing_hand = '✍️';

        $toned = SkinTone::applied($writing_hand, '5');

        $this -> assertSame(2, mb_strlen($toned), 'the hand plus one modifier, and no selector left over');
        $this -> assertFalse(str_contains($toned, mb_chr(0xFE0F, 'UTF-8')));
    }
}
