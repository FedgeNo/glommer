<?php

declare(strict_types=1);

/**
 * Sharing is handing someone the permalink. A post that came from another
 * server has one worth passing on, and it is not this server's copy - so those
 * carry no share button, and everything else does, signed in or not.
 *
 * The client rebuilds the same bar from JSON (scripts/Post.js), so the rule
 * lives in two places and has to agree in both; tests/js/PostTest.js covers
 * the other half.
 */
class PostShareButtonTest extends TestCase
{
    private function barFor(bool $remote): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $bar = new PostActionBar();
        $bar -> postId = 7;
        $bar -> postUserId = 2;
        $bar -> postUsername = 'someone';
        $bar -> replyCount = 0;
        $bar -> likeCount = 0;
        $bar -> remote = $remote;

        $element = $bar -> toDOM();
        HTMLObject::currentDocument() -> appendChild($element);

        return $element;
    }

    private function shareButtons(\DOMElement $bar): int
    {
        return new \DOMXPath(HTMLObject::currentDocument()) -> query('.//button[contains(@class, "PostShareButton")]', $bar) -> length;
    }

    public function testALocalPostAlwaysOffersShare(): void
    {
        $this -> assertSame(1, $this -> shareButtons($this -> barFor(false)));
    }

    public function testAPostFromAnotherServerOffersNoShare(): void
    {
        $this -> assertSame(0, $this -> shareButtons($this -> barFor(true)));
    }

    /**
     * The bar itself always renders - it is the row the client builds too, and
     * a card that has one server-side and not client-side is the drift this
     * pair of tests exists to catch.
     */
    public function testTheBarItselfIsAlwaysRendered(): void
    {
        $this -> assertTrue(str_contains($this -> barFor(true) -> getAttribute('class'), 'PostActionBar'));
        $this -> assertTrue(str_contains($this -> barFor(false) -> getAttribute('class'), 'PostActionBar'));
    }
}
