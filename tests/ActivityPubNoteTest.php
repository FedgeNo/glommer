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

        foreach (range(1, ActivityPubOutbox::PAGE_SIZE + 3) as $index) {
            self::post((int) $user -> userId, [['insert' => 'post ' . $index . "\n"]]);
        }

        $first = ActivityPubOutbox::activitiesFor($user, 1);
        $second = ActivityPubOutbox::activitiesFor($user, 2);

        $this -> assertSame(ActivityPubOutbox::PAGE_SIZE, count($first));
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
}
