<?php

declare(strict_types=1);

/**
 * Reading a post a relay named means waiting on somebody else's server, and
 * the inbox cannot afford to: two signed fetches at five seconds each, at the
 * rate the inbox already permits, is enough held PHP workers to exhaust the
 * pool and stop the site answering. So the inbox writes down what to read and
 * the federation worker reads it.
 *
 * What is queued here is best-effort - nobody asked for any of it - which is
 * what lets the queue be bounded and give up quickly, unlike an outbound
 * delivery somebody is waiting on.
 */
class RelayFetchTest extends DatabaseTestCase
{
    private function clearQueue(): void
    {
        DB::run('
DELETE
    FROM `RelayFetches`
');
        DB::run('
DELETE
    FROM `Relays`
');
    }

    private function subscribedRelay(): int
    {
        $actor_uri = 'https://relay.example/' . bin2hex(random_bytes(6));
        $accepted = 'accepted';

        DB::run('
INSERT INTO `Relays` (`actorURI`, `inboxURL`, `followActivityId`, `followObject`, `status`)
    VALUES (?, ?, ?, ?, ?)
', 'sssss', $actor_uri, $actor_uri . '/inbox', 'https://glommer.test/actor#follows/x', Relay::FOLLOW_PUBLIC, $accepted);

        return (int) mysqli_insert_id(DB::connection());
    }

    private function queueDepth(): int
    {
        return RelayFetch::pendingCount();
    }

    public function testAPostIsQueuedRatherThanRead(): void
    {
        $this -> clearQueue();

        RelayFetch::enqueue('https://elsewhere.example/notes/1', $this -> subscribedRelay());

        $this -> assertSame(1, $this -> queueDepth());

        $this -> clearQueue();
    }

    /** Two relays naming the same post is one thing to read, not two. */
    public function testTheSamePostQueuedTwiceIsQueuedOnce(): void
    {
        $this -> clearQueue();

        $first = $this -> subscribedRelay();
        $second = $this -> subscribedRelay();

        RelayFetch::enqueue('https://elsewhere.example/notes/2', $first);
        RelayFetch::enqueue('https://elsewhere.example/notes/2', $second);

        $this -> assertSame(1, $this -> queueDepth());

        $this -> clearQueue();
    }

    public function testWhatIsDueComesBackOldestFirst(): void
    {
        $this -> clearQueue();

        $relay_id = $this -> subscribedRelay();

        RelayFetch::enqueue('https://elsewhere.example/notes/first', $relay_id);
        RelayFetch::enqueue('https://elsewhere.example/notes/second', $relay_id);

        $due = RelayFetch::due();

        $this -> assertSame('https://elsewhere.example/notes/first', (string) $due[0] -> objectURI);
        $this -> assertSame('https://elsewhere.example/notes/second', (string) $due[1] -> objectURI);

        $this -> clearQueue();
    }

    public function testFinishingWithAPostTakesItOffTheQueue(): void
    {
        $this -> clearQueue();

        RelayFetch::enqueue('https://elsewhere.example/notes/3', $this -> subscribedRelay());
        RelayFetch::done((int) RelayFetch::due()[0] -> relayFetchId);

        $this -> assertSame(0, $this -> queueDepth());
    }

    /**
     * A failed read is worth one retry, and then it is dropped - there is
     * always more coming, and holding a post nobody is waiting for buys
     * nothing.
     */
    public function testAFailedReadIsRetriedOnceThenGivenUpOn(): void
    {
        $this -> clearQueue();

        RelayFetch::enqueue('https://elsewhere.example/notes/4', $this -> subscribedRelay());

        $queued = RelayFetch::due()[0];
        RelayFetch::failed((int) $queued -> relayFetchId, (int) $queued -> attempts);

        // Still queued, but not due yet - the retry is deferred.
        $this -> assertSame(1, $this -> queueDepth());
        $this -> assertSame([], RelayFetch::due());

        RelayFetch::failed((int) $queued -> relayFetchId, 1);

        $this -> assertSame(0, $this -> queueDepth());
    }

    public function testUnsubscribingDropsWhatWasQueuedForThatRelay(): void
    {
        // Through RelayFetches' foreign key: nothing is waiting on posts from
        // a firehose that has been turned off. The test database replays
        // schema.sql, so the cascade asserted here is the real one.
        $this -> clearQueue();
        $relay_id = $this -> subscribedRelay();

        RelayFetch::enqueue('https://elsewhere.example/notes/' . bin2hex(random_bytes(4)), $relay_id);

        $this -> assertSame(1, $this -> queueDepth());

        DB::run('
DELETE
    FROM `Relays`
    WHERE `relayId` = ?
', 'i', $relay_id);

        $this -> assertSame(0, $this -> queueDepth());
    }
}
