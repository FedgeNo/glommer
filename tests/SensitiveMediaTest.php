<?php

declare(strict_types=1);

/**
 * Media classified as sensitive: shown when asked for, not before.
 *
 * The cover is a real <details>, so what matters is that the media ends up
 * inside one when the post is marked and outside one when it isn't - no
 * script has to run for either.
 */
class SensitiveMediaTest extends TestCase
{
    private static function renderedPost(int $sensitive): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $item = new ImageItem();
        $item -> itemId = 7;
        $item -> postId = 3;

        $post = new Post();
        $post -> postId = 3;
        $post -> userId = 1;
        $post -> sensitive = $sensitive;
        $post -> items = [$item];

        $content = $post -> contentElement() -> toDOM();
        HTMLObject::currentDocument() -> appendChild($content);

        return $content;
    }

    private static function query(\DOMElement $content, string $xpath): \DOMNodeList
    {
        return new \DOMXPath(HTMLObject::currentDocument()) -> query($xpath, $content);
    }

    public function testMarkedMediaSitsBehindACover(): void
    {
        $content = self::renderedPost(1);

        $this -> assertSame(1, self::query($content, './/details[@class="SensitiveMedia"]') -> length);
        $this -> assertSame(1, self::query($content, './/details[@class="SensitiveMedia"]//figure') -> length);
    }

    public function testTheCoverSaysWhatItIs(): void
    {
        // An unlabelled disclosure is a thing to click, not a decision.
        $content = self::renderedPost(1);
        $summary = self::query($content, './/details[@class="SensitiveMedia"]/summary');

        $this -> assertSame(1, $summary -> length);
        $this -> assertTrue(trim((string) $summary -> item(0) -> textContent) !== '');
    }

    public function testTheSummaryComesFirst(): void
    {
        // A <summary> that isn't the first child is not the disclosure control -
        // the browser makes one up and the real one becomes ordinary content.
        $content = self::renderedPost(1);
        $details = self::query($content, './/details[@class="SensitiveMedia"]') -> item(0);

        $this -> assertSame('summary', $details -> firstElementChild -> tagName);
    }

    public function testUnmarkedMediaIsJustShown(): void
    {
        $content = self::renderedPost(0);

        $this -> assertSame(0, self::query($content, './/details') -> length);
        $this -> assertSame(1, self::query($content, './/figure') -> length);
    }
}
