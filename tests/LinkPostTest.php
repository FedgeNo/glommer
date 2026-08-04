<?php

declare(strict_types=1);

/**
 * A link post carries a picture - the preview fetched from the page it points
 * at - and that picture is a FeedItem like any other. So "does this post have
 * an item" and "is this a media post" are different questions, and the edit
 * form turns on the second one: it hides the Link field from a media post,
 * because a media post never had a link to edit.
 *
 * Answering with the first question hid the Link field from link posts that
 * had a preview image. Saving then sent no link at all, the row's linkURL was
 * cleared, and the post kept its picture - so editing a link post's text
 * quietly turned it into an image post.
 */
class LinkPostTest extends TestCase
{
    private static function renderedCard(?string $link_url): \DOMElement
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());

        $item = new ImageItem();
        $item -> itemId = 7;
        $item -> postId = 3;

        $post = new Post();
        $post -> postId = 3;
        $post -> userId = 1;
        $post -> linkURL = $link_url;
        $post -> items = [$item];
        // The action bar runs its own queries; this test is about the card's
        // own attributes.
        $post -> showActions = false;

        // The attribute is only written for the post's own author, since
        // nobody else can open the edit form. Put back straight away: a signed
        // -in session left behind here sends the next test down paths that
        // query the database.
        $was = $_SESSION['userId'] ?? null;
        $_SESSION['userId'] = 1;

        try {
            $card = $post -> toDOM();
        } finally {
            if ($was === null) {
                unset($_SESSION['userId']);
            } else {
                $_SESSION['userId'] = $was;
            }
        }

        HTMLObject::currentDocument() -> appendChild($card);

        return $card;
    }

    public function testAPostWithOnlyMediaIsAMediaPost(): void
    {
        $this -> assertSame('1', self::renderedCard(null) -> getAttribute('data-has-media'));
    }

    public function testALinkPostIsNotAMediaPostEvenWithItsPreviewPicture(): void
    {
        // Which is what keeps the Link field on the edit form, and the link on
        // the post once it is saved.
        $this -> assertSame('', self::renderedCard('https://example.com/') -> getAttribute('data-has-media'));
    }

    public function testALinkPostKeepsItsLinkOnTheCardForTheEditForm(): void
    {
        $this -> assertSame('https://example.com/', self::renderedCard('https://example.com/') -> getAttribute('data-link-url'));
    }
}
