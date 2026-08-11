<?php

declare(strict_types=1);

/**
 * Pictures, video and sound on a post that arrived from another server.
 *
 * The file stays where it was published; only its address is kept here, and
 * every viewer's request for it goes out from this server rather than from
 * their browser. So the two things worth proving are that an attachment
 * survives the trip at all, and that nothing a reader sends can steer what
 * gets fetched.
 */
class RemoteMediaTest extends DatabaseTestCase
{
    private static function remoteUser(): User
    {
        $actor = 'https://remote.invalid/users/r-' . bin2hex(random_bytes(5));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', 'r-' . bin2hex(random_bytes(6)) . '@remote.invalid', 'test-' . bin2hex(random_bytes(6)) . '@example.test', self::cheapHash('x'), $actor, 'key', $actor . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    /** @param array<int, array<string, mixed>> $attachments */
    private static function deliver(User $author, array $attachments, bool $sensitive = false): int
    {
        $object_uri = 'https://remote.invalid/statuses/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'actor' => $author -> remoteActorURI,
            'object' => [
                'type' => 'Note',
                'id' => $object_uri,
                'attributedTo' => $author -> remoteActorURI,
                'content' => '<p>look at this</p>',
                'sensitive' => $sensitive,
                'attachment' => $attachments,
            ],
        ], (string) $author -> remoteActorURI);

        $post = DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);

        return $post === null ? 0 : (int) $post -> postId;
    }

    /** @return FeedItem[] */
    private static function itemsOf(int $post_id): array
    {
        return FeedItem::itemsForPosts([$post_id])[$post_id] ?? [];
    }

    public function testAnImageOnAnInboundPostIsKept(): void
    {
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'type' => 'Document',
            'mediaType' => 'image/jpeg',
            'url' => 'https://remote.invalid/media/cat.jpg',
            'name' => 'A cat asleep on a keyboard',
        ]]);

        $items = self::itemsOf($post_id);

        $this -> assertSame(1, count($items));
        $this -> assertTrue($items[0] instanceof ImageItem);
        $this -> assertSame('https://remote.invalid/media/cat.jpg', $items[0] -> remoteURL);
        $this -> assertSame('A cat asleep on a keyboard', $items[0] -> altText);
    }

    public function testTheRenderedPostPointsAtThisServerNotTheirs(): void
    {
        // The whole purpose: a reader's browser must never be the thing that
        // asks the other server for the file.
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'mediaType' => 'image/png',
            'url' => 'https://remote.invalid/media/thing.png',
        ]]);

        $items = self::itemsOf($post_id);

        $this -> assertFalse(str_contains($items[0] -> srcURL(), 'remote.invalid'));
        $this -> assertTrue(str_contains($items[0] -> srcURL(), '/media-' . $items[0] -> itemId));
    }

    public function testVideoAndAudioArriveAsThemselves(): void
    {
        $author = self::remoteUser();
        $post_id = self::deliver($author, [
            ['mediaType' => 'video/mp4', 'url' => 'https://remote.invalid/media/clip.mp4'],
            ['mediaType' => 'audio/mpeg', 'url' => 'https://remote.invalid/media/song.mp3'],
        ]);

        $items = self::itemsOf($post_id);

        $this -> assertSame(2, count($items));
        $this -> assertTrue($items[0] instanceof VideoItem);
        $this -> assertTrue($items[1] instanceof AudioItem);
    }

    public function testAPlainHTTPAttachmentIsRefused(): void
    {
        // It would be fetched in the clear, on behalf of a reader who has no
        // idea it is happening.
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'mediaType' => 'image/jpeg',
            'url' => 'http://remote.invalid/media/cat.jpg',
        ]]);

        $this -> assertSame([], self::itemsOf($post_id));
    }

    public function testAScriptableImageTypeIsRefused(): void
    {
        // SVG carries script, and this would be served back from this site's
        // own origin.
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'mediaType' => 'image/svg+xml',
            'url' => 'https://remote.invalid/media/x.svg',
        ]]);

        $this -> assertSame([], self::itemsOf($post_id));
    }

    public function testAnUnservableTypeIsNotRescuedByTheObjectType(): void
    {
        // Saying "type: Image" alongside a mediaType we refuse must not talk us
        // back into it.
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'type' => 'Image',
            'mediaType' => 'image/svg+xml',
            'url' => 'https://remote.invalid/media/x.svg',
        ]]);

        $this -> assertSame([], self::itemsOf($post_id));
    }

    public function testAMissingMediaTypeFallsBackToTheObjectType(): void
    {
        // Not every server sends mediaType.
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'type' => 'Image',
            'url' => 'https://remote.invalid/media/plain',
        ]]);

        $this -> assertSame(1, count(self::itemsOf($post_id)));
    }

    public function testALinkObjectURLIsUnderstood(): void
    {
        $author = self::remoteUser();
        $post_id = self::deliver($author, [[
            'mediaType' => 'image/jpeg',
            'url' => ['type' => 'Link', 'href' => 'https://remote.invalid/media/linked.jpg'],
        ]]);

        $items = self::itemsOf($post_id);

        $this -> assertSame('https://remote.invalid/media/linked.jpg', $items[0] -> remoteURL);
    }

    public function testAPostCannotBringUnlimitedAttachments(): void
    {
        $author = self::remoteUser();
        $attachments = [];

        for ($index = 0; $index < 40; $index++) {
            $attachments[] = ['mediaType' => 'image/jpeg', 'url' => 'https://remote.invalid/media/' . $index . '.jpg'];
        }

        $this -> assertTrue(count(self::itemsOf(self::deliver($author, $attachments))) < 40);
    }

    public function testTheSendersClassificationIsHonoured(): void
    {
        // Their server is the only party that knows what it is sending.
        $author = self::remoteUser();

        $marked = self::deliver($author, [['mediaType' => 'image/jpeg', 'url' => 'https://remote.invalid/a.jpg']], true);
        $plain = self::deliver($author, [['mediaType' => 'image/jpeg', 'url' => 'https://remote.invalid/b.jpg']], false);

        $this -> assertSame(1, (int) DB::row('
SELECT `sensitive`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $marked) -> sensitive);

        $this -> assertSame(0, (int) DB::row('
SELECT `sensitive`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $plain) -> sensitive);
    }

    public function testTheProxyOnlyEverResolvesAnItemItHolds(): void
    {
        // There is no URL parameter to point somewhere else with; an id that
        // isn't a stored remote item resolves to nothing at all.
        $this -> assertNull(RemoteMedia::sourceFor(0));
        $this -> assertNull(RemoteMedia::sourceFor(999999999));
    }

    public function testALocalItemIsNotProxied(): void
    {
        // Its bytes are on this server's own disk; going out to the network for
        // them would be absurd, and the lookup says so.
        $author = self::remoteUser();
        $post_id = self::deliver($author, []);

        DB::run('
INSERT INTO `FeedItems` (`postId`, `type`)
    VALUES (?, ?)
', 'is', $post_id, 'ImageItem');

        $item_id = (int) mysqli_insert_id(DB::connection());

        $this -> assertNull(RemoteMedia::sourceFor($item_id));
    }
}
