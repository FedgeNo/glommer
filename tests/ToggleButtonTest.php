<?php

declare(strict_types=1);

/**
 * The buttons under a post that reword themselves.
 *
 * Pressing Like used to shove every button after it along, because the label
 * grew. The fix is that a button carries every wording it can show from the
 * start, hidden but measured, so its width is settled before anybody presses
 * anything. These hold that: the wordings have to actually be in the markup,
 * or the width is not reserved and the row jumps again.
 */
class ToggleButtonTest extends TestCase
{
    private function xpathOver(HTMLObject $object): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        HTMLObject::currentDocument() -> appendChild($object -> toDOM());

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    /** @return string[] */
    private function labelsOf(HTMLObject $button): array
    {
        $labels = [];

        foreach ($this -> xpathOver($button) -> query('//span[contains(@class, "ToggleButtonLabel")]') as $span) {
            $labels[] = $span -> textContent;
        }

        return $labels;
    }

    private function showingIn(HTMLObject $button): array
    {
        $showing = [];

        foreach ($this -> xpathOver($button) -> query('//span[contains(@class, "ToggleButtonLabel") and not(contains(@class, "Inactive"))]') as $span) {
            $showing[] = $span -> textContent;
        }

        return $showing;
    }

    /** A pair of glyphs: both present, exactly one of them showing. */
    public function testAPairCarriesBothGlyphsAndShowsOne(): void
    {
        $this -> assertSame(
            [PostTranslateButton::TRANSLATE, PostTranslateButton::SHOW_ORIGINAL],
            $this -> labelsOf(new PostTranslateButton())
        );
        $this -> assertSame([PostTranslateButton::TRANSLATE], $this -> showingIn(new PostTranslateButton()));
    }

    /**
     * A glyph is not a name. Every one of these shows a picture and nothing
     * else, so without this a screen reader announces "button" and stops.
     */
    public function testEveryWordlessActionSaysWhatItIs(): void
    {
        $buttons = [
            'Like' => new PostLikeButton(false, 0),
            'Unlike' => new PostLikeButton(true, 2),
            'Repost' => new PostRepostButton(false, 0),
            'Undo repost' => new PostRepostButton(true, 2),
            'Bookmark' => new PostBookmarkButton(false),
            'Remove bookmark' => new PostBookmarkButton(true),
            'Pin' => new PostPinButton(false),
            'Unpin' => new PostPinButton(true),
            'Translate' => new PostTranslateButton(),
            'Share' => new PostShareButton('https://example.test/users/someone/1'),
            'Quote' => new PostQuoteButton(1),
        ];

        foreach ($buttons as $name => $button) {
            $element = $this -> xpathOver($button) -> query('//button | //a') -> item(0);

            $this -> assertSame($name, $element -> getAttribute('aria-label'));
            $this -> assertSame($name, $element -> getAttribute('title'), 'and says it on hover too');
        }
    }

    /**
     * What is on, for anybody not reading the colour. Only the like button's
     * glyph could have carried its own state and it does not either - a thumb
     * has no emptied form - so this is the whole of it.
     */
    public function testATogglesStateIsReadableWithoutSeeingTheColour(): void
    {
        foreach ([
            [new PostLikeButton(true, 1), 'true'],
            [new PostLikeButton(false, 0), 'false'],
            [new PostRepostButton(true, 1), 'true'],
            [new PostBookmarkButton(true), 'true'],
            [new PostPinButton(false), 'false'],
        ] as [$button, $expected]) {
            $pressed = $this -> xpathOver($button) -> query('//button') -> item(0) -> getAttribute('aria-pressed');

            $this -> assertSame($expected, $pressed);
        }
    }

    /** The undo state wears the same red as everything else that takes something away. */
    public function testTheUndoStateIsMarkedAsOne(): void
    {
        foreach ([new PostLikeButton(true, 1), new PostRepostButton(true, 1), new PostBookmarkButton(true), new PostPinButton(true)] as $button) {
            $classes = (string) $this -> xpathOver($button) -> query('//button') -> item(0) -> getAttribute('class');

            $this -> assertTrue(str_contains($classes, 'Removing'), $classes);
        }

        foreach ([new PostLikeButton(false, 0), new PostBookmarkButton(false)] as $button) {
            $classes = (string) $this -> xpathOver($button) -> query('//button') -> item(0) -> getAttribute('class');

            $this -> assertFalse(str_contains($classes, 'Removing'), $classes);
        }
    }

    /**
     * A count cannot be listed in advance - it is whatever it is, and it moves
     * under the reader - so these reserve the width of the widest form instead
     * and rewrite one live label inside it.
     */
    public function testACountedLabelReservesRoomForTheCountItDoesNotKnowYet(): void
    {
        $thumb = PostLikeButton::GLYPH;

        $this -> assertSame([$thumb, $thumb . ' XXX'], $this -> labelsOf(new PostLikeButton(false, 0)));
        $this -> assertSame([$thumb], $this -> showingIn(new PostLikeButton(false, 0)));

        $this -> assertSame([$thumb . ' 7', $thumb . ' XXX'], $this -> labelsOf(new PostLikeButton(true, 7)));
        $this -> assertSame([$thumb . ' 7'], $this -> showingIn(new PostLikeButton(true, 7)));

        $this -> assertSame([$thumb . ' 12'], $this -> showingIn(new PostLikeButton(true, 12)));
    }

    /**
     * The reserved wording is furniture. A reader on a screen reader would
     * otherwise be told the button says two things, one of them nonsense.
     */
    public function testTheReservedWordingIsHiddenFromAssistiveTech(): void
    {
        $reserved = $this -> xpathOver(new PostLikeButton(false, 0))
            -> query('//span[contains(@class, "ToggleButtonReservation")]');

        $this -> assertSame(1, $reserved -> length);
        $this -> assertSame('true', $reserved -> item(0) -> getAttribute('aria-hidden'));
        $this -> assertTrue(str_contains((string) $reserved -> item(0) -> getAttribute('class'), 'Inactive'), 'and never shown');
    }

    /** The identity the CSS and the click handlers key on, unchanged by all this. */
    public function testEachButtonKeepsItsOwnNameBesideTheSharedOnes(): void
    {
        $classes = (string) $this -> xpathOver(new PostLikeButton(true, 3))
            -> query('//button') -> item(0) -> getAttribute('class');

        foreach (['Button', 'ToggleButton', 'PostLikeButton', 'Removing'] as $name) {
            $this -> assertTrue(str_contains($classes, $name), $name . ' is on the button');
        }
    }
}
