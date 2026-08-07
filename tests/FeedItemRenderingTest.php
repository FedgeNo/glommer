<?php

declare(strict_types=1);

/**
 * What an attached file says about itself in the markup: the alt a screen
 * reader speaks, and the raw author-written value plus row identity the post
 * editor reads back - which must stay distinct, because the spoken fallback
 * would otherwise be read back as the author's own words - and what a video
 * from another server offers in place of the thumbnail it does not have.
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

    private function remoteVideo(): VideoItem
    {
        $row = new FeedItemData();
        $row -> itemId = 43;
        $row -> postId = 7;
        $row -> type = 'VideoItem';
        $row -> remoteURL = 'https://remote.example/media/clip.mp4';

        $item = FeedItem::fromRow($row);
        $item -> deferred = false;

        return $item;
    }

    /**
     * Nothing thumbnails remote media - it isn't ours to transcode - so a
     * video from another server has no poster to give. Setting the attribute
     * anyway is not an empty poster: it is a TypeError that takes down every
     * page the video appears on, which is what the relay feed is.
     */
    public function testARemoteVideoRendersWithNoPosterRatherThanAnEmptyOne(): void
    {
        $element = $this -> elementFor($this -> remoteVideo());
        $video = new \DOMXPath(HTMLObject::currentDocument()) -> query('.//video', $element) -> item(0);

        $this -> assertNotNull($video);
        $this -> assertFalse($video -> hasAttribute('poster'));
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
