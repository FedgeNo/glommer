<?php

declare(strict_types=1);

/**
 * The labelled fields a remote account publishes about itself.
 *
 * They arrive as somebody else's HTML, in an array this server did not write,
 * and end up rendered on a profile here - so what matters is that the markup
 * does not survive the trip, that the array cannot be arbitrarily large, and
 * that what comes back off a row is the shape the renderer expects however the
 * row was written.
 */
class RemoteActorFieldsTest extends TestCase
{
    /** @param array<int, array<string, mixed>> $attachments */
    private static function of(array $attachments): array
    {
        return RemoteActorFields::fromAttachments($attachments);
    }

    public function testANameAndValuePairSurvivesAsWords(): void
    {
        $fields = self::of([
            ['type' => 'PropertyValue', 'name' => 'Website', 'value' => '<a href="https://example.test">example.test</a>'],
        ]);

        $this -> assertSame([['name' => 'Website', 'value' => 'example.test']], $fields);
    }

    /**
     * The markup is not carried across. A value arrives as a fragment the far
     * server built for its own page; rendering it here would be this site
     * showing another's HTML, and the renderer makes its own links anyway.
     */
    public function testNobodyElsesMarkupComesWithIt(): void
    {
        $fields = self::of([
            ['type' => 'PropertyValue', 'name' => 'Bio', 'value' => '<b>bold</b> and <script>alert(1)</script>plain'],
        ]);

        $this -> assertSame(1, count($fields));
        $this -> assertFalse(str_contains($fields[0]['value'], '<'), $fields[0]['value']);
        $this -> assertFalse(str_contains($fields[0]['value'], 'alert'), 'a script tag is not words');
    }

    /** Anything that is not a labelled field is not one. */
    public function testOnlyPropertyValuesCount(): void
    {
        $this -> assertSame([], self::of([
            ['type' => 'Hashtag', 'name' => '#cats', 'href' => 'https://example.test/tags/cats'],
            ['type' => 'Image', 'url' => 'https://example.test/header.png'],
            ['name' => 'no type at all', 'value' => 'x'],
            'not even an array',
        ]));
    }

    /** A field with nothing on one side of it is not a pair. */
    public function testAHalfFieldIsDropped(): void
    {
        $this -> assertSame([], self::of([
            ['type' => 'PropertyValue', 'name' => 'Website', 'value' => '   '],
            ['type' => 'PropertyValue', 'name' => '', 'value' => 'orphaned'],
        ]));
    }

    /**
     * A profile cannot make this server hold an arbitrary list. Nothing stops
     * a stranger publishing a thousand of them.
     */
    public function testAnEndlessListIsCutShort(): void
    {
        $many = [];

        for ($i = 0; $i < 200; $i++) {
            $many[] = ['type' => 'PropertyValue', 'name' => 'Field ' . $i, 'value' => 'value ' . $i];
        }

        $this -> assertTrue(count(self::of($many)) <= 8, 'kept ' . count(self::of($many)));
    }

    /** A value that arrived as paragraphs is one line, since it renders as a row. */
    public function testAValueIsOneLine(): void
    {
        $fields = self::of([
            ['type' => 'PropertyValue', 'name' => 'About', 'value' => "<p>one</p><p>two</p>"],
        ]);

        $this -> assertFalse(str_contains($fields[0]['value'], "\n"), $fields[0]['value']);
    }

    /** What is written to a row comes back as what the renderer expects. */
    public function testFieldsSurviveTheRoundTrip(): void
    {
        $fields = self::of([
            ['type' => 'PropertyValue', 'name' => 'Pronouns', 'value' => 'they/them'],
            ['type' => 'PropertyValue', 'name' => 'Website', 'value' => '<a href="https://example.test">example.test</a>'],
        ]);

        $this -> assertSame($fields, RemoteActorFields::decode(RemoteActorFields::encode($fields)));
    }

    /** An account with none stores nothing rather than an empty list. */
    public function testNoFieldsIsNoValue(): void
    {
        $this -> assertNull(RemoteActorFields::encode([]));
        $this -> assertSame([], RemoteActorFields::decode(null));
    }

    /** A row holding something else is not trusted to be what it claims. */
    public function testAMalformedRowDecodesToNothingUsable(): void
    {
        $this -> assertSame([], RemoteActorFields::decode('not json'));
        $this -> assertSame([], RemoteActorFields::decode('{"name":"not a list"}'));
        $this -> assertSame([], RemoteActorFields::decode('[{"name":"Website"}]'));
        $this -> assertSame([], RemoteActorFields::decode('[["Website","example.test"]]'));
    }
}
