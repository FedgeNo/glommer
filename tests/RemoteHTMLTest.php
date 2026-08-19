<?php

declare(strict_types=1);

class RemoteHTMLTest extends TestCase
{
    public function testBlockAndBreakBoundariesBecomeReadableLines(): void
    {
        $this -> assertSame(
            "One\nTwo\nThree",
            RemoteHTML::toPlainText('<p>One<br>Two</p><blockquote>Three</blockquote>')
        );
    }

    public function testMarkupIsRemovedAfterEntitiesArePreservedAsText(): void
    {
        $this -> assertSame(
            '<b> & bold',
            RemoteHTML::toPlainText('<p>&lt;b&gt; &amp; <strong>bold</strong></p>')
        );
    }

    public function testNestedBlocksCollapseToAtMostOneBlankLine(): void
    {
        $this -> assertSame(
            "One\n\nTwo",
            RemoteHTML::toPlainText('<div><p>One</p></div><div><p>Two</p></div>')
        );
    }

    public function testMalformedRemoteBytesCannotLeaveInvalidUTF8(): void
    {
        $plain = RemoteHTML::toPlainText("<p>before \xC3\x28 after</p>");

        $this -> assertTrue(mb_check_encoding($plain, 'UTF-8'));
        $this -> assertTrue(str_contains($plain, 'before'));
        $this -> assertTrue(str_contains($plain, 'after'));
    }
}
