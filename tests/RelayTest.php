<?php

declare(strict_types=1);

/**
 * A relay is a stranger pushing other servers' posts at this one, so what it
 * is allowed to do here is narrow: only a subscription the far side actually
 * granted counts, only the answer to a Follow this server sent can grant one,
 * and what arrives goes to a feed of its own rather than into the feeds people
 * read to see the accounts they chose.
 */
class RelayTest extends DatabaseTestCase
{
    /**
     * Every DB test in a run shares one database, and an accepted relay is
     * delivered to by every later fan-out - so these own the table for the
     * length of a test and hand it back empty. Without that, a relay left
     * behind here quietly changes what other classes count.
     */
    private function clearRelays(): void
    {
        DB::run('
DELETE
    FROM `Relays`
');
    }

    private function subscribe(string $actor_uri, string $status = 'pending'): int
    {
        DB::run('
INSERT INTO `Relays` (`actorURI`, `inboxURL`, `followActivityId`, `followObject`, `status`)
    VALUES (?, ?, ?, ?, ?)
', 'sssss', $actor_uri, $actor_uri . '/inbox', 'https://glommer.test/activitypub/actor#follows/abcd', Relay::FOLLOW_PUBLIC, $status);

        return (int) mysqli_insert_id(DB::connection());
    }

    public function testAnAcceptedRelayIsSubscribedAndAPendingOneIsNot(): void
    {
        $this -> clearRelays();

        $this -> subscribe('https://pending.example/actor');
        $this -> subscribe('https://live.example/actor', 'accepted');

        // Until the far side answers, anything arriving is unsolicited -
        // taking its posts on the strength of a request we merely sent would
        // let anyone push a firehose at an address an admin once typed.
        $this -> assertFalse(Relay::isSubscribed('https://pending.example/actor'));
        $this -> assertTrue(Relay::isSubscribed('https://live.example/actor'));
        $this -> assertFalse(Relay::isSubscribed('https://stranger.example/actor'));

        $this -> clearRelays();
    }

    /**
     * Only accepted relays are fed this server's posts. A pending one has not
     * agreed to carry anything.
     */
    public function testOnlyAnAcceptedRelayIsDeliveredTo(): void
    {
        $this -> clearRelays();

        $this -> subscribe('https://pending.example/actor');
        $this -> subscribe('https://live.example/actor', 'accepted');

        $this -> assertSame(['https://live.example/actor/inbox'], Relay::deliveryInboxes());

        $this -> clearRelays();
    }

    public function testAnAnswerIsMatchedOnTheFollowThisServerSent(): void
    {
        $this -> clearRelays();

        $this -> subscribe('https://live.example/actor');

        $this -> assertNotNull(Relay::answering('https://live.example/actor', 'https://glommer.test/activitypub/actor#follows/abcd'));

        // A different activity id, or a different actor claiming the same one,
        // answers nothing - otherwise a server could grant itself a
        // subscription by asserting a reply nobody asked for.
        $this -> assertNull(Relay::answering('https://live.example/actor', 'https://glommer.test/activitypub/actor#follows/other'));
        $this -> assertNull(Relay::answering('https://elsewhere.example/actor', 'https://glommer.test/activitypub/actor#follows/abcd'));

        $this -> clearRelays();
    }

    public function testAcceptingMakesTheSubscriptionLive(): void
    {
        $this -> clearRelays();

        $relay_id = $this -> subscribe('https://live.example/actor');

        Relay::accepted($relay_id);

        $this -> assertTrue(Relay::isSubscribed('https://live.example/actor'));

        $this -> clearRelays();
    }

    /**
     * A refused subscription is removed rather than parked: one that is never
     * coming is not a state worth showing, and subscribing again is what
     * asking again should mean.
     */
    public function testRejectingRemovesTheSubscription(): void
    {
        $this -> clearRelays();

        $relay_id = $this -> subscribe('https://refused.example/actor');

        Relay::rejected($relay_id);

        $this -> assertNull(Relay::byActorURI('https://refused.example/actor'));
    }

    /**
     * The firehose is its own feed. A relayed post must not reach the main
     * feed - nobody here asked for it - and the RelayPosts row is what keeps
     * the two apart.
     */
    public function testARelayedPostIsMarkedAsSuchAndStaysOutOfTheMainFeed(): void
    {
        $this -> clearRelays();

        $relay_id = $this -> subscribe('https://live.example/actor', 'accepted');
        $author_id = self::createUser();

        // Remote-origin, the way an ingested post is stored.
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `remoteObjectURI`)
    VALUES (?, ?, ?)
', 'iss', $author_id, 'from the firehose', 'https://elsewhere.example/notes/1');
        $post_id = (int) mysqli_insert_id(DB::connection());

        Relay::recordPost($post_id, $relay_id);

        $result = mysqli_stmt_get_result(DB::run('
SELECT `relayId`
    FROM `RelayPosts`
    WHERE `postId` = ?
', 'i', $post_id));

        $this -> assertSame($relay_id, (int) mysqli_fetch_assoc($result)['relayId']);

        // The main feed selects only local posts, which is what keeps every
        // relayed one out of it however many arrive.
        $in_main_feed = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `Posts`
    WHERE `postId` = ? AND `remoteObjectURI` IS NULL
', 'i', $post_id));

        $this -> assertSame(0, (int) mysqli_fetch_assoc($in_main_feed)['count']);

        $this -> clearRelays();
    }

    /** Two relays naming the same post is one post, listed once. */
    public function testTheSamePostArrivingTwiceIsRecordedOnce(): void
    {
        $this -> clearRelays();

        $first = $this -> subscribe('https://one.example/actor', 'accepted');
        $second = $this -> subscribe('https://two.example/actor', 'accepted');
        $author_id = self::createUser();

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `remoteObjectURI`)
    VALUES (?, ?, ?)
', 'iss', $author_id, 'passed around', 'https://elsewhere.example/notes/2');
        $post_id = (int) mysqli_insert_id(DB::connection());

        Relay::recordPost($post_id, $first);
        Relay::recordPost($post_id, $second);

        $result = mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `count`
    FROM `RelayPosts`
    WHERE `postId` = ?
', 'i', $post_id));

        $this -> assertSame(1, (int) mysqli_fetch_assoc($result)['count']);

        $this -> clearRelays();
    }
}
