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

    /** A pair of wordings: both present, exactly one of them showing. */
    public function testAPairCarriesBothWordingsAndShowsOne(): void
    {
        $this -> assertSame(['Bookmark', 'Unbookmark'], $this -> labelsOf(new PostBookmarkButton(false)));
        $this -> assertSame(['Bookmark'], $this -> showingIn(new PostBookmarkButton(false)));
        $this -> assertSame(['Unbookmark'], $this -> showingIn(new PostBookmarkButton(true)));

        $this -> assertSame(['Pin', 'Unpin'], $this -> labelsOf(new PostPinButton(false)));
        $this -> assertSame(['Unpin'], $this -> showingIn(new PostPinButton(true)));

        $this -> assertSame(['Translate', 'Show original'], $this -> labelsOf(new PostTranslateButton()));
        $this -> assertSame(['Translate'], $this -> showingIn(new PostTranslateButton()));
    }

    /**
     * A count cannot be listed in advance - it is whatever it is, and it moves
     * under the reader - so these reserve the width of the widest form instead
     * and rewrite one live label inside it.
     */
    public function testACountedLabelReservesRoomForTheCountItDoesNotKnowYet(): void
    {
        $this -> assertSame(['Like', 'Unlike (XXX)'], $this -> labelsOf(new PostLikeButton(false, 0)));
        $this -> assertSame(['Like'], $this -> showingIn(new PostLikeButton(false, 0)));

        $this -> assertSame(['Unlike (7)', 'Unlike (XXX)'], $this -> labelsOf(new PostLikeButton(true, 7)));
        $this -> assertSame(['Unlike (7)'], $this -> showingIn(new PostLikeButton(true, 7)));

        $this -> assertSame(['Repost', 'Unrepost (XXX)'], $this -> labelsOf(new PostRepostButton(false, 0)));
        $this -> assertSame(['Unrepost (12)'], $this -> showingIn(new PostRepostButton(true, 12)));
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
