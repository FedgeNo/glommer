<?php

declare(strict_types=1);

/**
 * A link written into a sentence has somewhere to put words on either side of
 * it, even where this language wants none.
 *
 * Word order is a fact about a language, not about a sentence. English says
 * "Log in to reply", and a language that puts the verb last needs the words
 * before the link instead - but a sentence assembled as link-then-text has no
 * slot in front of the link to translate into, so the order is settled in the
 * markup before any translator sees it.
 *
 * The empty slot costs nothing: an empty text node renders as nothing at all.
 * What it buys is that moving the words is a translation rather than a change
 * to the code, which is the whole difference between i18n being possible and
 * being a rewrite.
 */
class LinkInASentenceTest extends TestCase
{
    /** @return \DOMElement the rendered element, in a fresh document */
    private static function render(HTMLObject $object): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $object -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return $element;
    }

    /**
     * Every anchor in a block that also holds words has a node on both sides
     * of it.
     */
    private function assertLinksCanMove(HTMLObject $object, string $what): void
    {
        $element = self::render($object);
        $anchors = (new \DOMXPath(HTMLObject::currentDocument())) -> query('//a', $element);

        $this -> assertTrue($anchors -> length > 0, $what . ' has a link to check');

        foreach ($anchors as $anchor) {
            $this -> assertNotNull(
                $anchor -> previousSibling,
                $what . ': nothing before the link, so no language can put words there'
            );
            $this -> assertNotNull(
                $anchor -> nextSibling,
                $what . ': nothing after the link, so no language can put words there'
            );
        }
    }

    public function testTheLoginPromptCanBeSaidInAnyOrder(): void
    {
        $this -> assertLinksCanMove(new LoginPrompt('reply'), 'LoginPrompt');
    }

    public function testTheRepostAttributionCanBeSaidInAnyOrder(): void
    {
        $this -> assertLinksCanMove(new RepostAttribution('someone', 'Someone'), 'RepostAttribution');
    }

    public function testTheWayBackToTheLocationsCanBeSaidInAnyOrder(): void
    {
        $this -> assertLinksCanMove(new MoreLocationsLink(), 'MoreLocationsLink');
    }

    /** The empty slot is empty: it must not put a space into the sentence. */
    public function testAnEmptySlotAddsNothingToWhatIsRead(): void
    {
        $element = self::render(new MoreLocationsLink());

        $this -> assertSame('See more locations', $element -> textContent);
    }
}
