<?php

declare(strict_types=1);

/**
 * How a post leaves this server. The body has to be the body the site itself
 * renders, the addressing has to say public, and a post that came in from
 * somewhere else must never go back out under our name.
 */
class ActivityPubNoteTest extends DatabaseTestCase
{
    private static function user(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    /** @param array<int, array<string, mixed>> $ops */
    private static function post(int $user_id, array $ops, ?string $title = null, ?int $parent_id = null, ?string $remote_uri = null): Post
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `parentId`, `title`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?, ?, ?)
', 'iissss', $user_id, $parent_id, $title, 'plain text', json_encode($ops), $remote_uri);

        $post_id = (int) mysqli_insert_id(DB::connection());

        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id);
    }

    /**
     * A post is announced the moment it is written, from the Post the endpoint
     * filled in by hand - not one selected back out of the row it just wrote.
     * So every column the document asks about has to be answerable on an
     * object no query ever hydrated, which is a different thing from every
     * other test here and the one shape that goes wrong unnoticed.
     */
    public function testAPostAssembledForPublishingIsPublishedAsItsAuthorsOwn(): void
    {
        $user = self::user();
        $written = self::post((int) $user -> userId, [['insert' => "fresh off the composer\n"]]);

        // The shape api/create-post.php hands to the publisher.
        $post = new Post();
        $post -> postId = $written -> postId;
        $post -> userId = (int) $user -> userId;
        $post -> description = 'plain text';
        $post -> descriptionDelta = json_encode([['insert' => "fresh off the composer\n"]]);
        $post -> createdAt = date('Y-m-d H:i:s');
        $post -> author = $user;

        $activity = ActivityPubNote::createActivity($post, $user);

        $this -> assertNotNull($activity);
        $this -> assertSame('Create', $activity['type']);
        $this -> assertSame('<p>fresh off the composer</p>', $activity['object']['content']);
    }

    public function testTheBodyIsTheRenderedPostWithoutThePageWrapper(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "hello there\n"]]);

        $document = ActivityPubNote::document($post, $user);

        $this -> assertSame('<p>hello there</p>', $document['content']);
        // .PostBody is how this site lays a body out, not part of what was
        // written - a receiving server has its own frame to put it in.
        $this -> assertFalse(str_contains((string) $document['content'], 'PostBody'));
    }

    public function testFormattingSurvivesTheTripOut(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [
            ['insert' => 'plain and '],
            ['insert' => 'bold', 'attributes' => ['bold' => true]],
            ['insert' => "\n"],
        ]);

        $document = ActivityPubNote::document($post, $user);

        $this -> assertTrue(str_contains((string) $document['content'], '<strong>bold</strong>'));
    }

    public function testAnUntitledPostIsANote(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "no title\n"]]);

        $document = ActivityPubNote::document($post, $user);

        $this -> assertSame('Note', $document['type']);
        $this -> assertFalse(isset($document['name']));
    }

    public function testATitledPostIsAnArticleThatKeepsItsTitle(): void
    {
        // A Note has nowhere to put a title. Dropping it or folding it into the
        // body would both lose what the author actually wrote.
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "body\n"]], 'A Real Title');

        $document = ActivityPubNote::document($post, $user);

        $this -> assertSame('Article', $document['type']);
        $this -> assertSame('A Real Title', $document['name']);
    }

    public function testAClassifiedPostSaysSoOnTheWire(): void
    {
        // Without this the far side shows the media unasked, which is the one
        // thing the classification exists to stop.
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "careful\n"]]);

        $this -> assertFalse(ActivityPubNote::document($post, $user)['sensitive']);

        Post::classify((int) $post -> postId, true);
        $classified = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $post -> postId);

        $this -> assertTrue(ActivityPubNote::document($classified, $user)['sensitive']);
    }

    private static function attach(int $post_id, string $type): void
    {
        DB::run('
INSERT INTO `FeedItems` (`postId`, `type`)
    VALUES (?, ?)
', 'is', $post_id, $type);
    }

    private static function withItems(Post $post, User $author): Post
    {
        $loaded = Post::fromRowWithItems($post);
        $loaded -> author = $author;

        return $loaded;
    }

    public function testAPostThatIsOneVideoIsPublishedAsAVideo(): void
    {
        // PeerTube publishes this way and a player on the other side goes
        // looking for it - a note with something attached is not the same thing.
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "watch\n"]]);
        self::attach((int) $post -> postId, 'VideoItem');

        $document = ActivityPubNote::document(self::withItems($post, $user), $user);

        $this -> assertSame('Video', $document['type']);
        $this -> assertFalse(isset($document['attachment']), 'the media is the object, not an attachment on it');
    }

    public function testAVideoCarriesBothThePageAndTheFile(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "watch\n"]]);
        self::attach((int) $post -> postId, 'VideoItem');

        $document = ActivityPubNote::document(self::withItems($post, $user), $user);
        $types = array_column($document['url'], 'mediaType');

        $this -> assertTrue(in_array('text/html', $types, true), 'a person needs the page');
        $this -> assertTrue(in_array('video/mp4', $types, true), 'a player needs the file');
    }

    public function testAPostThatIsOneAudioFileIsPublishedAsAudio(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "listen\n"]]);
        self::attach((int) $post -> postId, 'AudioItem');

        $this -> assertSame('Audio', ActivityPubNote::document(self::withItems($post, $user), $user)['type']);
    }

    public function testAVideoAlongsideOtherMediaStaysANote(): void
    {
        // Publishing it as a video would leave the photo with nowhere to go.
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "mixed\n"]]);
        self::attach((int) $post -> postId, 'VideoItem');
        self::attach((int) $post -> postId, 'ImageItem');

        $document = ActivityPubNote::document(self::withItems($post, $user), $user);

        $this -> assertSame('Note', $document['type']);
        $this -> assertSame(2, count($document['attachment']));
    }

    public function testAPostThatIsOneImageIsStillANote(): void
    {
        // Only video and audio become objects in their own right; an image post
        // is an ordinary note that happens to carry a picture.
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "look\n"]]);
        self::attach((int) $post -> postId, 'ImageItem');

        $document = ActivityPubNote::document(self::withItems($post, $user), $user);

        $this -> assertSame('Note', $document['type']);
        $this -> assertSame(1, count($document['attachment']));
    }

    public function testATitledVideoKeepsItsTitle(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "watch\n"]], 'My Film');
        self::attach((int) $post -> postId, 'VideoItem');

        $document = ActivityPubNote::document(self::withItems($post, $user), $user);

        $this -> assertSame('Video', $document['type']);
        $this -> assertSame('My Film', $document['name']);
    }

    public function testEverythingIsAddressedPublicly(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "public\n"]]);

        $document = ActivityPubNote::document($post, $user);

        $this -> assertSame([ActivityPubActor::PUBLIC_AUDIENCE], $document['to']);
        $this -> assertSame([ActivityPubActor::followersFor($user)], $document['cc']);
    }

    public function testThePostIdIsItsPermalink(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "x\n"]]);

        $expected = ServerURL::absolute('/users/' . $user -> slug . '/' . (int) $post -> postId);

        $document = ActivityPubNote::document($post, $user);

        $this -> assertSame($expected, $document['id']);
        $this -> assertSame($expected, $document['url']);
    }

    public function testAPostFromElsewhereIsNeverRepublishedByUs(): void
    {
        // It already has an id on its own server. Sending it out under ours
        // would be claiming someone else's writing.
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "theirs\n"]], null, null, 'https://mastodon.social/users/them/statuses/1');

        $this -> assertNull(ActivityPubNote::document($post, $user));
        $this -> assertNull(ActivityPubNote::createActivity($post, $user));
    }

    public function testAnUneditedPostCarriesNoUpdatedTime(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "fresh\n"]]);

        $this -> assertFalse(isset(ActivityPubNote::document($post, $user)['updated']));
    }

    public function testAnEditedPostSaysWhenItChanged(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "edited\n"]]);

        DB::run('
UPDATE `Posts`
    SET `editedAt` = NOW()
    WHERE `postId` = ?
', 'i', (int) $post -> postId);

        $reloaded = DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $post -> postId);

        $this -> assertTrue(isset(ActivityPubNote::document($reloaded, $user)['updated']));
    }

    public function testAReplyToOurOwnPostPointsAtItsPermalink(): void
    {
        $user = self::user();
        $parent = self::post((int) $user -> userId, [['insert' => "parent\n"]]);
        $reply = self::post((int) $user -> userId, [['insert' => "reply\n"]], null, (int) $parent -> postId);

        $document = ActivityPubNote::document($reply, $user);

        $this -> assertSame(
            ServerURL::absolute('/users/' . $user -> slug . '/' . (int) $parent -> postId),
            $document['inReplyTo']
        );
    }

    public function testAReplyToARemotePostPointsAtTheOriginalNotOurCopy(): void
    {
        // Otherwise the thread forks at our boundary: every other server would
        // see a reply to something they have never heard of.
        $user = self::user();
        $remote_uri = 'https://mastodon.social/users/them/statuses/' . bin2hex(random_bytes(4));
        $parent = self::post((int) $user -> userId, [['insert' => "theirs\n"]], null, null, $remote_uri);
        $reply = self::post((int) $user -> userId, [['insert' => "our reply\n"]], null, (int) $parent -> postId);

        $this -> assertSame($remote_uri, ActivityPubNote::document($reply, $user)['inReplyTo']);
    }

    public function testATopLevelPostRepliesToNothing(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "top\n"]]);

        $this -> assertFalse(isset(ActivityPubNote::document($post, $user)['inReplyTo']));
    }

    public function testTheCreateActivityWrapsTheObjectAndKeepsItsAddressing(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "wrapped\n"]]);

        $activity = ActivityPubNote::createActivity($post, $user);

        $this -> assertSame('Create', $activity['type']);
        $this -> assertSame(ActivityPubActor::uriFor($user), $activity['actor']);
        $this -> assertSame($activity['object']['to'], $activity['to']);

        // The activity and the object are different things and need different
        // ids, or a receiver cannot tell them apart.
        $this -> assertFalse($activity['id'] === $activity['object']['id']);
    }

    public function testADeleteNamesTheObjectAsATombstone(): void
    {
        $user = self::user();
        $activity = ActivityPubNote::deleteActivity('https://glommer.test/users/x/1', $user);

        $this -> assertSame('Delete', $activity['type']);
        $this -> assertSame('Tombstone', $activity['object']['type']);
        $this -> assertSame('https://glommer.test/users/x/1', $activity['object']['id']);
    }

    public function testTheOutboxPagesAndNeverExceedsItsPageSize(): void
    {
        $user = self::user();

        foreach (range(1, ActivityPubCollection::PAGE_SIZE + 3) as $index) {
            self::post((int) $user -> userId, [['insert' => 'post ' . $index . "\n"]]);
        }

        $first = ActivityPubOutbox::activitiesFor($user, 1);
        $second = ActivityPubOutbox::activitiesFor($user, 2);

        $this -> assertSame(ActivityPubCollection::PAGE_SIZE, count($first));
        $this -> assertSame(3, count($second));

        // Inside a collection the context is carried by the collection itself.
        $this -> assertFalse(isset($first[0]['@context']));
    }

    public function testTheOutboxCountIgnoresPostsThatCameFromElsewhere(): void
    {
        $user = self::user();
        self::post((int) $user -> userId, [['insert' => "ours\n"]]);
        self::post((int) $user -> userId, [['insert' => "theirs\n"]], null, null, 'https://mastodon.social/x/' . bin2hex(random_bytes(4)));

        $this -> assertSame(1, Post::publishedCountFor((int) $user -> userId));
    }

    /** Puts a post at a point, the way the composer does. */
    private static function locate(Post $post, float $latitude, float $longitude): void
    {
        DB::run('
INSERT INTO `PostLocations` (`postId`, `latitude`, `longitude`)
    VALUES (?, ?, ?)
', 'idd', (int) $post -> postId, $latitude, $longitude);
    }

    /**
     * A post that says where it was written says so on the wire.
     *
     * ActivityStreams has carried a location since the beginning, so that is
     * what goes out - coordinates, not prose a receiver would have to parse
     * back into them.
     */
    public function testAPostWithALocationCarriesItAsAPlace(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "Sunny here\n"]]);

        self::locate($post, 49.2827, -123.1207);

        $document = ActivityPubNote::document(self::reload($post), $user);

        $this -> assertSame('Place', $document['location']['type'] ?? null);
        $this -> assertSame(49.2827, (float) ($document['location']['latitude'] ?? 0));
        $this -> assertSame(-123.1207, (float) ($document['location']['longitude'] ?? 0));
    }

    /**
     * And says it where a person will actually see it.
     *
     * Mastodon reads neither the property nor the coordinates in it, and most
     * implementations follow Mastodon - so a post whose only mention of where
     * it came from is that property arrives somewhere nobody can see it.
     */
    public function testTheLocationIsAlsoALinkInTheContent(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "Sunny here\n"]]);

        self::locate($post, 49.2827, -123.1207);

        $content = ActivityPubNote::document(self::reload($post), $user)['content'];

        $this -> assertTrue(str_contains($content, 'Sunny here'), 'the post is still the post');
        $this -> assertTrue(str_contains($content, '/map?lat=49.2827'), 'and it leads back to the point: ' . $content);
        $this -> assertTrue(str_starts_with($content, '<p>Sunny here'), 'the body comes first: ' . $content);
    }

    /**
     * The whole content is one document rather than two glued together - the
     * body rendered, then the location appended to that same render, so the
     * escaping and the shape of an element are decided in one place.
     */
    public function testTheBodyAndTheLocationAreOneRender(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "5 > 3 & true\n"]]);

        self::locate($post, 49.2827, -123.1207);

        $content = ActivityPubNote::document(self::reload($post), $user)['content'];

        // Escaped once, by the renderer - not twice, and not left raw.
        $this -> assertTrue(str_contains($content, '5 &gt; 3 &amp; true'), $content);
        $this -> assertFalse(str_contains($content, '&amp;gt;'), 'escaped twice: ' . $content);
    }

    /** A post with no location says nothing about one. */
    public function testAPostWithoutALocationSaysNothingAboutOne(): void
    {
        $user = self::user();
        $post = self::post((int) $user -> userId, [['insert' => "Nowhere in particular\n"]]);

        $document = ActivityPubNote::document($post, $user);

        $this -> assertFalse(isset($document['location']), 'no location property');
        $this -> assertFalse(str_contains($document['content'], '/map?lat='), 'and no link to one');
    }

    /** The post as a query gives it back, so postId is set for the lookup. */
    private static function reload(Post $post): Post
    {
        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $post -> postId);
    }
}
