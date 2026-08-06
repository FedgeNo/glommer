<?php

declare(strict_types=1);

/**
 * What an attached image says about itself in the markup: the alt a screen
 * reader speaks, and the raw author-written value plus row identity the post
 * editor reads back - which must stay distinct, because the spoken fallback
 * would otherwise be read back as the author's own words.
 */
class FeedItemRenderingTest extends TestCase
{
    private function elementFor(FeedItem $item): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $element = $item -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return $element;
    }

    private function image(?string $alt_text): ImageItem
    {
        $row = new FeedItemData();
        $row -> itemId = 42;
        $row -> postId = 7;
        $row -> type = 'ImageItem';
        $row -> altText = $alt_text;

        $item = FeedItem::fromRow($row);
        $item -> deferred = false;

        return $item;
    }

    public function testAuthorAltTextIsSpokenAndReadableBack(): void
    {
        $element = $this -> elementFor($this -> image('A cat asleep on a radiator'));
        $img = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//img', $element) -> item(0);

        $this -> assertSame('A cat asleep on a radiator', $img -> getAttribute('alt'));
        $this -> assertSame('A cat asleep on a radiator', $element -> getAttribute('data-alt-text'));
        $this -> assertSame('42', $element -> getAttribute('data-item-id'));
    }

    public function testTheFallbackAltIsSpokenButNeverReadableBack(): void
    {
        $element = $this -> elementFor($this -> image(null));
        $img = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//img', $element) -> item(0);

        $this -> assertSame('Image', $img -> getAttribute('alt'));
        $this -> assertFalse($element -> hasAttribute('data-alt-text'));
    }
}
