<?php

declare(strict_types=1);

/**
 * Switching the composer between rich and markdown has to be safe in both
 * directions, and what makes it safe is one property: a delta written out as
 * markdown and read back is the same delta. The delta is what gets stored, so
 * that is the thing that must not drift.
 *
 * The reverse is deliberately not asserted. Several spellings mean one thing -
 * *x* and _x_, - and * for a bullet - so markdown read in and written back out
 * comes back in the site's spelling rather than the author's. That is
 * normalisation, not loss: the meaning, and the delta, are identical.
 */
class MarkdownRoundTripTest extends TestCase
{
    private const NEWLINE = "\n";

    /**
     * Everything the composer's toolbar can produce, alone and combined.
     *
     * @return array<string, array[]>
     */
    private function deltas(): array
    {
        $newline = self::NEWLINE;

        return [
            'plain words' => [['insert' => 'just words'], ['insert' => $newline]],
            'every emphasis at once' => [
                ['insert' => 'all', 'attributes' => ['bold' => true, 'italic' => true, 'underline' => true, 'strike' => true]],
                ['insert' => $newline],
            ],
            'bold and italic sharing a marker' => [
                ['insert' => 'bi', 'attributes' => ['bold' => true, 'italic' => true]],
                ['insert' => $newline],
            ],
            'underline, which markdown has no spelling for' => [
                ['insert' => 'under', 'attributes' => ['underline' => true]],
                ['insert' => $newline],
            ],
            'a run among plain text' => [
                ['insert' => 'hi '],
                ['insert' => 'bold', 'attributes' => ['bold' => true]],
                ['insert' => ' and '],
                ['insert' => 'under', 'attributes' => ['underline' => true]],
                ['insert' => $newline],
            ],
            'a formatted link' => [
                ['insert' => 'link', 'attributes' => ['bold' => true, 'link' => 'https://example.test/a']],
                ['insert' => $newline],
            ],
            'headings and a quote' => [
                ['insert' => 'a'], ['insert' => $newline, 'attributes' => ['header' => 1]],
                ['insert' => 'b'], ['insert' => $newline, 'attributes' => ['header' => 3]],
                ['insert' => 'c'], ['insert' => $newline, 'attributes' => ['blockquote' => true]],
            ],
            'both kinds of list' => [
                ['insert' => 'first'], ['insert' => $newline, 'attributes' => ['list' => 'bullet']],
                ['insert' => 'second'], ['insert' => $newline, 'attributes' => ['list' => 'ordered']],
            ],
            'a run of code blocks and a line after' => [
                ['insert' => 'one'], ['insert' => $newline, 'attributes' => ['code-block' => true]],
                ['insert' => 'two'], ['insert' => $newline, 'attributes' => ['code-block' => true]],
                ['insert' => 'after'], ['insert' => $newline],
            ],
            'markers inside a code block are content' => [
                ['insert' => 'x * y and `ticks`'],
                ['insert' => $newline, 'attributes' => ['code-block' => true]],
            ],
            'an inline code span' => [
                ['insert' => 'mixed '],
                ['insert' => 'c', 'attributes' => ['code' => true]],
                ['insert' => ' end'],
                ['insert' => $newline],
            ],
            'a formula and words' => [
                ['insert' => ['formula' => 'a^2+b^2']],
                ['insert' => ' tail'],
                ['insert' => $newline],
            ],
            'marker characters somebody actually typed' => [
                ['insert' => 'under_score and *star* and [brackets]'],
                ['insert' => $newline],
            ],
            'a backslash somebody actually typed' => [
                ['insert' => 'back\\slash'],
                ['insert' => $newline],
            ],
        ];
    }

    /**
     * The property the mode selector rests on. Written out, read back, and the
     * delta is the one that went in - for every format the editor can make.
     */
    public function testEveryDeltaSurvivesBeingWrittenAsMarkdown(): void
    {
        foreach ($this -> deltas() as $description => $delta) {
            $markdown = DeltaToMarkdown::convert($delta);
            $returned = HTMLToDelta::convert(Markdown::toHTML($markdown));

            $this -> assertSame(
                json_encode(Delta::sanitize($delta)),
                json_encode($returned),
                $description . ' came back changed, as ' . json_encode($markdown)
            );
        }
    }

    /** The dialect itself, pinned where it departs from CommonMark. */
    public function testUnderlineIsWrittenWithTheMarkerCommonMarkSpendsOnStrong(): void
    {
        $delta = [['insert' => 'x', 'attributes' => ['underline' => true]], ['insert' => self::NEWLINE]];

        $this -> assertSame('__x__', DeltaToMarkdown::convert($delta));
        $this -> assertSame('<p><u>x</u></p>', Markdown::toHTML('__x__'));
        $this -> assertSame('<p><strong>x</strong></p>', Markdown::toHTML('**x**'));
    }

    /**
     * One line is one block, unlike CommonMark, which joins wrapped lines into
     * a paragraph. A delta ends a block at every newline, so joining them
     * would merge two blocks into one and break the property above.
     */
    public function testALineIsABlock(): void
    {
        $ops = HTMLToDelta::convert(Markdown::toHTML('one' . self::NEWLINE . 'two'));

        $this -> assertSame(
            'one' . self::NEWLINE . 'two' . self::NEWLINE,
            implode('', array_map(static fn (array $op): string => (string) $op['insert'], $ops))
        );
    }

    /** A marker in somebody's words is words, not a marker. */
    public function testAnEscapedMarkerStaysText(): void
    {
        $this -> assertSame('<p>*not italic*</p>', Markdown::toHTML('\\*not italic\\*'));
    }
}
