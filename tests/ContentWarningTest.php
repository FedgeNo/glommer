<?php

declare(strict_types=1);

/**
 * The warning a post arrives with, and what it covers.
 *
 * `sensitive` covers media and leaves the writing readable, which is right for
 * the flag it answers to. A warning is not that flag: the commonest one on the
 * network is a spoiler, and the thing being spoiled is the words. Covering the
 * pictures and printing the spoiler underneath honours the letter of the
 * warning and defeats the whole point of it, silently, on every post.
 */
class ContentWarningTest extends DatabaseTestCase
{
    private static function shadowUser(string $actor_uri): User
    {
        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
', 'sssssssi',
            'test-remote-' . bin2hex(random_bytes(6)),
            'test-' . bin2hex(random_bytes(6)) . '@example.test',
            password_hash('x', PASSWORD_DEFAULT),
            'Remote Person',
            $actor_uri,
            'not-a-real-key',
            $actor_uri . '/inbox',
            1
        );

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    /** Delivers a note carrying $summary, and hands back the stored post. */
    private static function deliver(?string $summary, string $content = 'the spoiler itself'): ?Post
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        $object = [
            'type' => 'Note',
            'id' => $object_uri,
            'attributedTo' => $actor_uri,
            'content' => $content,
            'sensitive' => $summary !== null,
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
        ];

        if ($summary !== null) {
            $object['summary'] = $summary;
        }

        ActivityPubInbox::process(['type' => 'Create', 'object' => $object], $actor_uri);

        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);
    }

    /** @return \DOMXPath over a freshly rendered post */
    private static function render(Post $post): \DOMXPath
    {
        (new \ReflectionProperty(HTMLObject::class, 'document')) -> setValue(null, new \DOMDocument());
        HTMLObject::currentDocument() -> appendChild($post -> toDOM());

        return new \DOMXPath(HTMLObject::currentDocument());
    }

    public function testTheWarningTextArrivesWithThePost(): void
    {
        $post = self::deliver('Spoilers for the finale');

        $this -> assertNotNull($post);
        $this -> assertSame('Spoilers for the finale', $post -> contentWarning);
    }

    /** A post nobody warned about carries no warning rather than an empty one. */
    public function testAPostWithNoSummaryCarriesNoWarning(): void
    {
        $post = self::deliver(null);

        $this -> assertNotNull($post);
        $this -> assertNull($post -> contentWarning);
    }

    /** An empty summary is nothing, not a gate with no words on it. */
    public function testAnEmptySummaryIsNotAWarning(): void
    {
        $post = self::deliver('   ');

        $this -> assertNotNull($post);
        $this -> assertNull($post -> contentWarning);
    }

    /** Markup in a warning is flattened, the same as any other inbound content. */
    public function testMarkupInAWarningIsFlattened(): void
    {
        $post = self::deliver('<p>Spoilers &amp; such</p>');

        $this -> assertNotNull($post);
        $this -> assertSame('Spoilers & such', $post -> contentWarning);
    }

    /** The whole body goes behind the gate - the point of the exercise. */
    public function testTheWordsGoBehindTheWarning(): void
    {
        $post = self::deliver('Spoilers for the finale');
        $xpath = self::render(Post::fromRowWithItems($post));

        $gate = $xpath -> query('//details[contains(@class, "ContentWarning")]');

        $this -> assertSame(1, $gate -> length);
        $this -> assertSame(
            'Spoilers for the finale',
            trim((string) $xpath -> query('//summary[contains(@class, "ContentWarningSummary")]') -> item(0) ?-> textContent)
        );
        $this -> assertTrue(
            str_contains((string) $gate -> item(0) ?-> textContent, 'the spoiler itself'),
            'the words are inside the gate, not beside it'
        );
    }

    /**
     * One gate, not two. A sender that writes a warning also sets `sensitive`
     * - Mastodon always does - so without this the media sits under a cover
     * inside the warning and the reader has to ask for it twice.
     */
    public function testAWarnedPostDoesNotAlsoCoverItsMediaSeparately(): void
    {
        $post = self::deliver('Spoilers for the finale');
        $post = Post::fromRowWithItems($post);

        DB::run('
INSERT INTO `FeedItems` (`postId`, `type`, `remoteURL`)
    VALUES (?, ?, ?)
', 'iss', (int) $post -> postId, 'ImageItem', 'https://remote.test/media/1.jpg');

        $reloaded = Post::fromRowWithItems(DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $post -> postId));

        $xpath = self::render($reloaded);

        $this -> assertSame(1, $xpath -> query('//details[contains(@class, "ContentWarning")]') -> length);
        $this -> assertSame(
            0,
            $xpath -> query('//details[contains(@class, "SensitiveMedia")]') -> length,
            'the warning already asked; asking again inside it is one gate too many'
        );
    }

    /** And a post with no warning is not put behind an empty one. */
    public function testAPostWithNoWarningHasNoGate(): void
    {
        $post = self::deliver(null);
        $xpath = self::render(Post::fromRowWithItems($post));

        $this -> assertSame(0, $xpath -> query('//details[contains(@class, "ContentWarning")]') -> length);
        $this -> assertTrue(str_contains((string) HTMLObject::currentDocument() -> textContent, 'the spoiler itself'));
    }

    /** An edit that adds a warning applies it; one that drops it takes it away. */
    public function testAnEditCanAddAndRemoveTheWarning(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        $note = [
            'type' => 'Note',
            'id' => $object_uri,
            'attributedTo' => $actor_uri,
            'content' => 'words',
            'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
        ];

        ActivityPubInbox::process(['type' => 'Create', 'object' => $note], $actor_uri);

        ActivityPubInbox::process(['type' => 'Update', 'object' => $note + ['summary' => 'Added later']], $actor_uri);
        $this -> assertSame('Added later', self::warningOn($object_uri));

        ActivityPubInbox::process(['type' => 'Update', 'object' => $note], $actor_uri);
        $this -> assertNull(self::warningOn($object_uri));
    }

    private static function warningOn(string $object_uri): ?string
    {
        return DB::row('
SELECT `contentWarning`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri) ?-> contentWarning;
    }
}
