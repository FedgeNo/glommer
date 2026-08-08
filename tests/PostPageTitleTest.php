<?php

declare(strict_types=1);

/**
 * What heads a post's page when the post has no title of its own.
 *
 * The body cut at fifty characters is a poor title - it ends mid-sentence and
 * often mid-word - and most posts open with a line that was meant as an
 * opening. So the opening line is used where there is one and it is short
 * enough to be a title; everything else still falls back to the cut, because
 * a first line longer than the cut would only be cut too.
 *
 * The lines live in the delta. Posts.description is the flattened form and
 * has none left, which is why this cannot be answered from it.
 */
class PostPageTitleTest extends TestCase
{
    private const NEWLINE = "\n";

    private function post(string $body, ?string $title = null): Post
    {
        $ops = [['insert' => $body]];

        $post = new Post();
        $post -> title = $title;
        $post -> descriptionDelta = json_encode(['ops' => $ops]);
        $post -> description = Delta::plainText($ops);

        return $post;
    }

    public function testAPostWithATitleKeepsIt(): void
    {
        $this -> assertSame(
            'A Real Title',
            $this -> post('Body text here.' . self::NEWLINE, 'A Real Title') -> pageTitle()
        );
    }

    /** The case this exists for: a short opening line above a long body. */
    public function testAShortOpeningLineBecomesTheTitle(): void
    {
        $post = $this -> post(
            'Coffee thoughts' . self::NEWLINE
            . 'Then a much longer second paragraph that runs well past any reasonable title length.' . self::NEWLINE
        );

        $this -> assertSame('Coffee thoughts', $post -> pageTitle());
    }

    /**
     * Nothing to take a line from, so the body is cut as it always was - a
     * post written as one long paragraph has no opening line to speak of.
     */
    public function testOneLongParagraphIsStillCut(): void
    {
        $title = $this -> post(
            'This is a single paragraph with no line breaks at all and it just keeps going well past fifty.' . self::NEWLINE
        ) -> pageTitle();

        $this -> assertTrue(str_ends_with($title, '…'), 'a body with no opening line is cut');
        $this -> assertTrue(mb_strlen($title) <= 51, 'and cut to the same length as before');
    }

    /**
     * A first line longer than the cut would be truncated anyway, so it is no
     * better than the body and the body is what gets used.
     */
    public function testAnOpeningLineTooLongToBeATitleIsNotUsedAsOne(): void
    {
        $title = $this -> post(
            'This very first line is itself far longer than fifty characters could ever be.' . self::NEWLINE
            . 'Second line.' . self::NEWLINE
        ) -> pageTitle();

        $this -> assertTrue(str_ends_with($title, '…'));
    }

    /** One short line and nothing after it is already the whole post. */
    public function testASingleShortLineIsUsedWhole(): void
    {
        $this -> assertSame('Just this.', $this -> post('Just this.' . self::NEWLINE) -> pageTitle());
    }

    /** A post carrying only a picture is headed by whoever posted it. */
    public function testAPostWithNoWordsIsHeadedByItsAuthor(): void
    {
        $author = new User();
        $author -> title = 'Ada';

        $post = $this -> post('');
        $post -> author = $author;

        $this -> assertSame('Ada\'s Post', $post -> pageTitle());
    }
}
