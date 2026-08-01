<?php

declare(strict_types=1);

/**
 * Custom emoji: a :shortcode: that means a picture rather than a character.
 *
 * The whole difference from the Unicode table is that nobody can resolve one
 * they were not told about. Every server has its own set, a post carries the
 * meaning with it as an Emoji tag, and that tag only ever speaks for its own
 * server.
 */
class CustomEmojiTest extends DatabaseTestCase
{
    private static function tag(string $name, string $url): array
    {
        return ['type' => 'Emoji', 'name' => $name, 'icon' => ['type' => 'Image', 'url' => $url]];
    }

    private static function objectURI(string $host = 'remote.invalid'): string
    {
        return 'https://' . $host . '/statuses/' . bin2hex(random_bytes(5));
    }

    public function testATagTeachesWhatAShortcodeMeans(): void
    {
        $uri = self::objectURI();

        $learned = CustomEmoji::learnFrom([self::tag(':blobcat:', 'https://remote.invalid/blobcat.png')], $uri);

        $this -> assertSame(['blobcat' => 'https://remote.invalid/blobcat.png'], $learned);
        $this -> assertSame('https://remote.invalid/blobcat.png', CustomEmoji::forObject($uri)['blobcat']);
    }

    public function testTheSameNameOnTwoServersIsTwoDifferentPictures(): void
    {
        // A single global table would make whichever arrived first silently
        // stand in for the other.
        $first = self::objectURI('one.invalid');
        $second = self::objectURI('two.invalid');

        CustomEmoji::learnFrom([self::tag(':blobcat:', 'https://one.invalid/a.png')], $first);
        CustomEmoji::learnFrom([self::tag(':blobcat:', 'https://two.invalid/b.png')], $second);

        $this -> assertSame('https://one.invalid/a.png', CustomEmoji::forObject($first)['blobcat']);
        $this -> assertSame('https://two.invalid/b.png', CustomEmoji::forObject($second)['blobcat']);
    }

    public function testASecondTagForTheSameNameUpdatesIt(): void
    {
        $uri = self::objectURI('update.invalid');

        CustomEmoji::learnFrom([self::tag(':pic:', 'https://update.invalid/old.png')], $uri);
        CustomEmoji::learnFrom([self::tag(':pic:', 'https://update.invalid/new.png')], $uri);

        $this -> assertSame('https://update.invalid/new.png', CustomEmoji::forObject($uri)['pic']);
    }

    public function testANonHTTPSImageIsRefused(): void
    {
        // A tag is content from another server, and this URL ends up in an img
        // on our page.
        $uri = self::objectURI('bad.invalid');

        CustomEmoji::learnFrom([
            self::tag(':a:', 'http://bad.invalid/a.png'),
            self::tag(':b:', 'javascript:alert(1)'),
            self::tag(':c:', 'data:image/png;base64,AAAA'),
        ], $uri);

        $this -> assertSame([], CustomEmoji::forObject($uri));
    }

    public function testANameThatCouldNeverBeTypedIsRefused(): void
    {
        // Storing it would fill the table with entries nothing can reach.
        $uri = self::objectURI('names.invalid');

        CustomEmoji::learnFrom([
            self::tag(':has space:', 'https://names.invalid/a.png'),
            self::tag('::', 'https://names.invalid/b.png'),
            self::tag(':' . str_repeat('x', 100) . ':', 'https://names.invalid/c.png'),
        ], $uri);

        $this -> assertSame([], CustomEmoji::forObject($uri));
    }

    public function testANonEmojiTagIsIgnored(): void
    {
        $uri = self::objectURI('tags.invalid');

        CustomEmoji::learnFrom([
            ['type' => 'Mention', 'name' => '@someone', 'href' => 'https://tags.invalid/users/someone'],
            ['type' => 'Hashtag', 'name' => '#thing', 'href' => 'https://tags.invalid/tags/thing'],
        ], $uri);

        $this -> assertSame([], CustomEmoji::forObject($uri));
    }

    public function testAPostWithNoOriginHasNoCustomEmoji(): void
    {
        // A local post: nothing here defines any, so there is nothing to look up.
        $this -> assertSame([], CustomEmoji::forObject(null));
    }

    public function testACustomEmojiRendersAsAnImage(): void
    {
        $html = DeltaRenderer::toHTML(
            [['insert' => "look :blobcat: here\n"]],
            ['blobcat' => 'https://remote.invalid/blobcat.png']
        );

        $this -> assertTrue(str_contains($html, '<img class="CustomEmoji" src="https://remote.invalid/blobcat.png"'));
        $this -> assertTrue(str_contains($html, 'alt=":blobcat:"'), 'the shortcode is the only description there is');
        $this -> assertTrue(str_contains($html, 'look '), 'the surrounding text survives');
        $this -> assertTrue(str_contains($html, ' here'));
    }

    public function testACustomNameBeatsTheUnicodeTable(): void
    {
        // A tag is the sending server saying what a shortcode means in THIS
        // post, which is a more specific claim than a table everyone shares.
        $html = DeltaRenderer::toHTML(
            [['insert' => ":smile:\n"]],
            ['smile' => 'https://remote.invalid/theirs.png']
        );

        $this -> assertTrue(str_contains($html, 'theirs.png'));
        $this -> assertFalse(str_contains($html, '😄'));
    }

    public function testUnicodeStillExpandsAlongsideACustomOne(): void
    {
        $html = DeltaRenderer::toHTML(
            [['insert' => ":smile: and :blobcat:\n"]],
            ['blobcat' => 'https://remote.invalid/blobcat.png']
        );

        $this -> assertTrue(str_contains($html, '😄'));
        $this -> assertTrue(str_contains($html, 'blobcat.png'));
    }

    public function testACustomEmojiInCodeIsLeftAlone(): void
    {
        $html = DeltaRenderer::toHTML(
            [['insert' => 'x = :blobcat:'], ['insert' => "\n", 'attributes' => ['code-block' => true]]],
            ['blobcat' => 'https://remote.invalid/blobcat.png']
        );

        $this -> assertTrue(str_contains($html, '<pre>x = :blobcat:</pre>'));
    }

    public function testAnUndeclaredNameStaysText(): void
    {
        $html = DeltaRenderer::toHTML([['insert' => "a :blobcat: b\n"]]);

        $this -> assertTrue(str_contains($html, ':blobcat:'));
    }
}
