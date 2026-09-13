<?php

declare(strict_types=1);

// Offline integration fixture: production parsers and handlers, canned HTTP,
// a separate disposable database, and no socket capable of sending a message.
if (PHP_SAPI !== 'cli') {
    exit(1);
}

class SafeHTTPFetcher
{
    public static array $responses = [];
    public static array $requests = [];

    public static function getJSON(string $url, array $headers, int $max_bytes, ?callable $per_request = null): ?array
    {
        self::$requests[] = ['url' => $url, 'headers' => $headers];

        if (!array_key_exists($url, self::$responses)) {
            throw new \RuntimeException('Unexpected HTTP request: ' . $url);
        }

        return self::$responses[$url];
    }

    public static function postJSON(string $url, string $body, array $headers, int $max_bytes): ?array
    {
        return ['body' => '', 'contentType' => null, 'url' => $url, 'urls' => [$url]];
    }
}

class WebSocketPusher
{
    public static function push(int $user_id, array $payload): void
    {
    }
}

spl_autoload_register(static function (string $class): void {
    $file = __DIR__ . '/../../src/classes/' . $class . '.php';

    if (is_file($file)) {
        require $file;
    }
});
require __DIR__ . '/../../src/functions.php';
require __DIR__ . '/../TestCase.php';
require __DIR__ . '/../DatabaseTestCase.php';
require __DIR__ . '/../TestDatabase.php';

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new \ErrorException($message, 0, $severity, $file, $line);
});

class FederationFetchCases extends DatabaseTestCase
{
    private function user(bool $remote = true): User
    {
        $id = self::createUser();

        if ($remote) {
            $uri = 'https://remote.test/users/' . $id;
            DB::run('UPDATE `Users` SET `remoteActorURI` = ?, `remoteActorInboxURL` = ?, `remoteActorPublicKeyPem` = ? WHERE `userId` = ?', 'sssi', $uri, $uri . '/inbox', 'fixture-key', $id);
        }

        return DB::row('SELECT * FROM `Users` WHERE `userId` = ?', 'User', 'i', $id);
    }

    private function uri(): string
    {
        return 'https://remote.test/notes/' . bin2hex(random_bytes(8));
    }

    private function note(User $author, ?string $uri = null): array
    {
        return ['id' => $uri ?? $this -> uri(), 'type' => 'Note', 'attributedTo' => $author -> remoteActorURI, 'content' => '<p>A remote post.</p>', 'to' => [ActivityPubActor::PUBLIC_AUDIENCE]];
    }

    private function deliver(array $object, User $author, array $envelope = []): void
    {
        ActivityPubInbox::process($envelope + ['type' => 'Create', 'object' => $object], $author -> remoteActorURI);
    }

    private function stored(string $uri): ?Post
    {
        return DB::row('SELECT * FROM `Posts` WHERE `remoteObjectURI` = ?', 'Post', 's', $uri);
    }

    private function response(string $uri, array $object, string $type = 'application/activity+json', ?array $urls = null): void
    {
        $urls ??= [$uri];
        SafeHTTPFetcher::$responses[$uri] = ['body' => json_encode($object, JSON_THROW_ON_ERROR), 'contentType' => $type, 'url' => $urls[count($urls) - 1], 'urls' => $urls];
    }

    private function localPost(User $author): Post
    {
        DB::run('INSERT INTO `Posts` (`userId`, `description`) VALUES (?, ?)', 'is', $author -> userId, 'A local post.');

        return DB::row('SELECT * FROM `Posts` WHERE `postId` = ?', 'Post', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    public function testActorNegotiationAndContentTypes(): void
    {
        $uri = 'https://remote.test/users/fetched';
        $actor = ['id' => $uri, 'type' => [ActivityStreams::CONTEXT . '#Service'], 'inbox' => $uri . '/inbox', 'publicKey' => ['publicKeyPem' => 'fixture-key'], 'preferredUsername' => 'fetched'];
        $this -> response($uri, $actor, 'text/html');
        $this -> assertNull(RemoteActor::fetch($uri));
        $this -> response($uri, $actor, 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"');
        $this -> assertSame('Service', RemoteActor::fetch($uri)['actorType']);
        $this -> assertSame([ActivityStreams::ACCEPT], SafeHTTPFetcher::$requests[0]['headers']);
        $actor['id'] = 'https://remote.test/users/someone-else';
        $this -> response($uri, $actor);
        $this -> assertNull(RemoteActor::fetch($uri), 'same host does not authorize an unrelated actor id');
    }

    public function testCanonicalActorCannotReplaceAnAliasSigningIdentity(): void
    {
        $uri = 'https://remote.test/users/alias';
        $canonical = 'https://remote.test/users/canonical';
        $this -> response($uri, ['id' => $canonical, 'inbox' => $canonical . '/inbox', 'publicKey' => ['publicKeyPem' => 'fixture-key']], urls: [$uri, $canonical]);
        $this -> assertSame($canonical, RemoteActor::fetch($uri)['id']);
        $this -> assertNull(RemoteActor::ensureKnown($uri));
        $this -> assertNull(User::byRemoteActorURI($canonical));
    }

    public function testWebFingerAcceptsProfiledJSONLDAndRejectsHTML(): void
    {
        $uri = 'https://example.org/.well-known/webfinger?resource=' . urlencode('acct:bob@example.org');
        $actor = 'https://remote.test/users/bob';
        $data = ['links' => [['rel' => 'self', 'type' => 'application/ld+json; profile="https://www.w3.org/ns/activitystreams"', 'href' => $actor]]];
        $this -> response($uri, $data, 'application/jrd+json; charset=utf-8');
        $this -> assertSame($actor, WebFinger::resolveActorURI('bob', 'example.org'));
        $this -> response($uri, $data, 'text/html');
        $this -> assertNull(WebFinger::resolveActorURI('bob', 'example.org'));
    }

    public function testPostFetchRejectsWrongMediaTypesJSONListsAndUnrelatedIds(): void
    {
        $author = $this -> user();
        $note = $this -> note($author);
        $uri = $note['id'];

        foreach (['text/html', 'application/json', 'application/ld+json'] as $type) {
            $this -> response($uri, $note, $type);
            $this -> assertNull(ActivityPubFetch::object($uri));
            $this -> assertFalse(ActivityPubInbox::fetchRelayedPost($uri, null));
            $this -> assertNull($this -> stored($uri));
        }

        $this -> response($uri, [$note]);
        $this -> assertNull(ActivityPubFetch::object($uri));
        $this -> response($uri, array_replace($note, ['id' => $this -> uri()]));
        $this -> assertNull(ActivityPubFetch::object($uri));
    }

    public function testCreateAndNoteAcceptAllSupportedTypeForms(): void
    {
        $author = $this -> user();

        foreach ([['Create', 'Note'], [['Create'], ['Note']], [ActivityStreams::CONTEXT . '#Create', ActivityStreams::CONTEXT . '#Note'], [['https://extension.test/Event', ActivityStreams::CONTEXT . '#Create'], ['https://extension.test/Post', 'Note']]] as [$activity_type, $object_type]) {
            $note = $this -> note($author);
            $note['type'] = $object_type;
            $this -> deliver($note, $author, ['type' => $activity_type]);
            $this -> assertNotNull($this -> stored($note['id']));
        }

        $note = $this -> note($author);
        $this -> deliver($note, $author, ['type' => ['Create', 'Delete']]);
        $this -> assertNull($this -> stored($note['id']));
    }

    public function testLinkedReactionsAndUndoChangeStoredCounts(): void
    {
        $author = $this -> user(false);
        $remote = $this -> user();
        $post = $this -> localPost($author);
        $target = ['type' => ['Link'], 'id' => $this -> uri(), 'href' => ActivityPubNote::uriFor($post, $author)];

        foreach (['Like' => 'likeCount', 'Announce' => 'repostCount'] as $type => $field) {
            $activity = ['id' => $this -> uri(), 'type' => [ActivityStreams::CONTEXT . '#' . $type], 'object' => $target];
            ActivityPubInbox::process($activity, $remote -> remoteActorURI);
            $counts = DB::row('SELECT `likeCount`, `repostCount` FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post -> postId);
            $this -> assertSame(1, $counts -> $field);
            ActivityPubInbox::process(['type' => ['Undo'], 'object' => $activity], $remote -> remoteActorURI);
            $counts = DB::row('SELECT `likeCount`, `repostCount` FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post -> postId);
            $this -> assertSame(0, $counts -> $field);
        }
    }

    public function testLinkedDeleteKeepsOwnershipChecksAndCancelsPendingCreates(): void
    {
        $author = $this -> user();
        $other = $this -> user();
        $note = $this -> note($author);
        $this -> deliver($note, $author);
        $delete = ['type' => [ActivityStreams::CONTEXT . '#Delete'], 'object' => ['type' => 'Link', 'href' => $note['id']]];
        ActivityPubInbox::process($delete, $other -> remoteActorURI);
        $this -> assertNotNull($this -> stored($note['id']));
        ActivityPubInbox::process($delete, $author -> remoteActorURI);
        $this -> assertNull($this -> stored($note['id']));
        $this -> assertTrue(RemoteObjectTombstone::isTombstoned($note['id']));
        $uri = $this -> uri();
        ActivityPubInbox::process(['type' => 'Create', 'object' => $uri], $author -> remoteActorURI);
        ActivityPubInbox::process(['type' => 'Delete', 'object' => ['href' => $uri]], $author -> remoteActorURI);
        $this -> assertSame(0, InboxFetch::pendingCount());
    }

    public function testLinkedParentIsAReplyAndMalformedParentIsNeverATopLevelPost(): void
    {
        $local = $this -> user(false);
        $post = $this -> localPost($local);
        $author = $this -> user();
        $note = $this -> note($author);
        $note['inReplyTo'] = ['type' => 'Link', 'href' => ActivityPubNote::uriFor($post, $local)];
        $this -> deliver($note, $author);
        $this -> assertSame($post -> postId, $this -> stored($note['id']) -> parentId);
        $note['id'] = $this -> uri();
        $note['inReplyTo'] = ['type' => 'Link', 'id' => $post -> postId];
        $this -> deliver($note, $author);
        $this -> assertNull($this -> stored($note['id']));
    }

    public function testReferencedCreatesAreQueuedWithoutFetchingThenStoredByTheWorker(): void
    {
        $author = $this -> user();

        foreach ([false, true] as $link) {
            $note = $this -> note($author);
            $note['attributedTo'] = ['type' => 'Link', 'href' => $author -> remoteActorURI];
            $note['type'] = [ActivityStreams::CONTEXT . '#Note'];
            $activity = ['type' => ['Create'], 'to' => [ActivityPubActor::PUBLIC_AUDIENCE], 'object' => $link ? ['type' => 'Link', 'href' => $note['id']] : $note['id']];
            ActivityPubInbox::process($activity, $author -> remoteActorURI);
            $this -> assertNull($this -> stored($note['id']));
            $row = InboxFetch::claim();
            $this -> assertSame($activity, json_decode($row -> activity, true));
            $this -> response($note['id'], $note);
            $this -> assertTrue(ActivityPubInbox::fetchCreate(json_decode($row -> activity, true), $row -> actorURI));
            $this -> assertNotNull($this -> stored($note['id']));
            InboxFetch::done($row -> inboxFetchId);
        }

        $this -> assertCount(2, SafeHTTPFetcher::$requests, 'only the worker fetched each object');
    }

    public function testDeferredCreateRetainsPrivateEnvelopeAndDeduplicatesMessages(): void
    {
        $author = $this -> user();
        $recipient = $this -> user(false);
        $note = $this -> note($author);
        unset($note['to']);
        $activity = ['type' => 'Create', 'object' => ['href' => $note['id']], 'to' => ['type' => 'Link', 'href' => ActivityPubActor::uriFor($recipient)]];
        ActivityPubInbox::process($activity, $author -> remoteActorURI);
        $row = InboxFetch::claim();
        $this -> response($note['id'], $note);
        $this -> assertTrue(ActivityPubInbox::fetchCreate(json_decode($row -> activity, true), $row -> actorURI));
        $this -> assertTrue(ActivityPubInbox::fetchCreate($activity, $row -> actorURI));
        $this -> assertNull($this -> stored($note['id']));
        $messages = DB::rows('SELECT `senderId`, `recipientId` FROM `Messages` WHERE `remoteObjectURI` = ?', 'Message', 's', $note['id']);
        $this -> assertCount(1, $messages);
        $this -> assertSame($recipient -> userId, $messages[0] -> recipientId);
        $this -> assertSame($author -> userId, $messages[0] -> senderId);
        InboxFetch::done($row -> inboxFetchId);
    }

    public function testDeferredVoteUsesALinkedPollWithoutCreatingAPost(): void
    {
        $local = $this -> user(false);
        $post = $this -> localPost($local);
        $poll = Poll::create($post -> postId, ['Yes', 'No'], false, 60);
        $author = $this -> user();
        $uri = $this -> uri();
        $vote = ['id' => $uri, 'type' => ['Note'], 'name' => 'Yes', 'attributedTo' => $author -> remoteActorURI, 'inReplyTo' => ['type' => 'Link', 'href' => ActivityPubNote::uriFor($post, $local)]];
        $this -> response($uri, $vote);
        $this -> assertTrue(ActivityPubInbox::fetchCreate(['type' => 'Create', 'object' => $uri], $author -> remoteActorURI));
        $this -> assertTrue($poll -> hasVoted($author -> userId));
        $this -> assertNull($this -> stored($uri));
    }

    public function testAttributionCannotBeBypassedWithLinksOrMissingFetchedAuthors(): void
    {
        $author = $this -> user();
        $other = $this -> user();

        foreach ([['href' => $other -> remoteActorURI], [$other -> remoteActorURI], null] as $attribution) {
            $note = $this -> note($author);
            $note['attributedTo'] = $attribution;
            $this -> deliver($note, $author);
            $this -> assertNull($this -> stored($note['id']));
            $this -> response($note['id'], $note);
            $this -> assertTrue(ActivityPubInbox::fetchCreate(['type' => 'Create', 'object' => $note['id']], $author -> remoteActorURI));
            $this -> assertNull($this -> stored($note['id']));
        }

        $note = $this -> note($author);
        unset($note['attributedTo']);
        $this -> response($note['id'], $note);
        $this -> assertTrue(ActivityPubInbox::fetchCreate(['type' => 'Create', 'object' => $note['id']], $author -> remoteActorURI));
        $this -> assertNull($this -> stored($note['id']));
    }

    public function testCanonicalRelayObjectsAndOriginalIdsBothWorkWithoutDuplicates(): void
    {
        $author = $this -> user();
        $original = $this -> uri();
        $canonical = $original . '/';
        $note = $this -> note($author, $canonical);
        $this -> response($original, $note, urls: [$original, $canonical]);
        $this -> assertTrue(ActivityPubInbox::fetchRelayedPost($original, null));
        $stored = $this -> stored($canonical);
        $this -> assertNotNull($stored);
        $this -> assertNull($this -> stored($original));
        $this -> assertTrue(ActivityPubInbox::fetchRelayedPost($original, null));
        $this -> assertSame($stored -> postId, $this -> stored($canonical) -> postId);
        $original = $this -> uri();
        $note = $this -> note($author, $original);
        $this -> response($original, $note, urls: [$original, $original . '/']);
        $this -> assertTrue(ActivityPubInbox::fetchRelayedPost($original, null));
        $this -> assertNotNull($this -> stored($original));
    }

    public function testDeferredThreadUsesCanonicalParentAndRetainsMentionNotifications(): void
    {
        $author = $this -> user();
        $recipient = $this -> user(false);
        $parent_alias = $this -> uri();
        $parent = $this -> note($author, $parent_alias . '/');
        $note = $this -> note($author);
        $note['inReplyTo'] = ['href' => $parent_alias];
        $note['tag'] = [['type' => [ActivityStreams::CONTEXT . '#Mention'], 'href' => ActivityPubActor::uriFor($recipient)]];
        $this -> deliver($note, $author);
        $this -> assertNull($this -> stored($note['id']));
        $row = InboxFetch::claim();
        $this -> response($parent_alias, $parent, urls: [$parent_alias, $parent['id']]);
        $this -> assertTrue(ActivityPubInbox::fetchCreate(json_decode($row -> activity, true), $row -> actorURI));
        $reply = $this -> stored($note['id']);
        $this -> assertSame($this -> stored($parent['id']) -> postId, $reply -> parentId);
        $this -> assertSame(1, $this -> stored($parent['id']) -> replyCount);
        $this -> assertSame([$recipient -> userId], FediverseNotice::mentionedLocalUserIds($note));
        $this -> assertNotNull(DB::row('SELECT `notificationId` FROM `Notifications` WHERE `userId` = ? AND `postId` = ? AND `type` = ?', 'Notification', 'iis', $recipient -> userId, $reply -> postId, 'mention'));
        $this -> assertCount(1, SafeHTTPFetcher::$requests, 'the original signed reply is retained; only its parent is fetched');
        InboxFetch::done($row -> inboxFetchId);
    }

    public function testDeferredReadsRetryButBannedSendersAndTombstonesDoNotFetch(): void
    {
        $author = $this -> user();
        $note = $this -> note($author);
        $activity = ['type' => 'Create', 'object' => $note['id']];
        SafeHTTPFetcher::$responses[$note['id']] = null;
        $this -> assertFalse(ActivityPubInbox::fetchCreate($activity, $author -> remoteActorURI));
        DB::run('UPDATE `Users` SET `banned` = 1 WHERE `userId` = ?', 'i', $author -> userId);
        $this -> assertTrue(ActivityPubInbox::fetchCreate($activity, $author -> remoteActorURI));
        $this -> assertCount(1, SafeHTTPFetcher::$requests);
        DB::run('UPDATE `Users` SET `banned` = 0 WHERE `userId` = ?', 'i', $author -> userId);
        $this -> deliver($note, $author);
        ActivityPubInbox::process(['type' => 'Delete', 'object' => $note['id']], $author -> remoteActorURI);
        $this -> assertTrue(ActivityPubInbox::fetchCreate($activity, $author -> remoteActorURI));
        $this -> assertCount(1, SafeHTTPFetcher::$requests);
    }

    public function testFetchedTombstonesAndPrivateObjectsAreNeverRelayPosts(): void
    {
        $author = $this -> user();
        $note = $this -> note($author);
        $note['type'] = ['Tombstone'];
        $this -> response($note['id'], $note);
        $this -> assertTrue(ActivityPubInbox::fetchRelayedPost($note['id'], null));
        $this -> assertNull($this -> stored($note['id']));
        $note['type'] = 'Note';
        $note['to'] = ['href' => 'https://other.test/private-recipient'];
        $this -> response($note['id'], $note);
        $this -> assertTrue(ActivityPubInbox::fetchRelayedPost($note['id'], null));
        $this -> assertNull($this -> stored($note['id']));
    }

    public function testFollowAcceptRejectAndUndoAcceptLinkedTargets(): void
    {
        $local = $this -> user(false);
        $remote = $this -> user();
        $follow_id = $this -> uri();
        $follow = ['id' => $follow_id, 'type' => [ActivityStreams::CONTEXT . '#Follow'], 'object' => ['type' => 'Link', 'href' => ActivityPubActor::uriFor($local)]];
        ActivityPubInbox::process($follow, $remote -> remoteActorURI);
        $this -> assertNotNull(DB::row('SELECT `localUserId` FROM `FediverseFollowers` WHERE `localUserId` = ? AND `remoteActorURI` = ?', 'stdClass', 'is', $local -> userId, $remote -> remoteActorURI));
        ActivityPubInbox::process(['type' => ['Undo'], 'object' => $follow], $remote -> remoteActorURI);
        $this -> assertNull(DB::row('SELECT `localUserId` FROM `FediverseFollowers` WHERE `localUserId` = ? AND `remoteActorURI` = ?', 'stdClass', 'is', $local -> userId, $remote -> remoteActorURI));

        DB::run('INSERT INTO `RemoteFollows` (`localUserId`, `remoteActorURI`, `followActivityId`, `status`) VALUES (?, ?, ?, ?)', 'isss', $local -> userId, $remote -> remoteActorURI, $follow_id, 'pending');
        $response = ['type' => [ActivityStreams::CONTEXT . '#Accept'], 'object' => ['type' => 'Link', 'href' => $follow_id]];
        ActivityPubInbox::process($response, $remote -> remoteActorURI);
        $this -> assertSame('accepted', DB::row('SELECT `status` FROM `RemoteFollows` WHERE `followActivityId` = ?', 'RemoteFollow', 's', $follow_id) -> status);
        $response['type'] = ['Reject'];
        ActivityPubInbox::process($response, $remote -> remoteActorURI);
        $this -> assertNull(DB::row('SELECT `status` FROM `RemoteFollows` WHERE `followActivityId` = ?', 'RemoteFollow', 's', $follow_id));
    }

    public function testQuestionAndUpdateAcceptFullTypeIRIs(): void
    {
        $author = $this -> user();
        $note = $this -> note($author);
        $note['type'] = [ActivityStreams::CONTEXT . '#Question'];
        $note['oneOf'] = [['type' => 'Note', 'name' => 'Yes'], ['type' => 'Note', 'name' => 'No']];
        $note['endTime'] = gmdate('c', time() + 3600);
        $this -> deliver($note, $author);
        $post = $this -> stored($note['id']);
        $this -> assertNotNull(Poll::forPost($post -> postId));
        $note['content'] = '<p>Updated question</p>';
        $this -> deliver($note, $author, ['type' => [ActivityStreams::CONTEXT . '#Update']]);
        $this -> assertSame('Updated question', trim($this -> stored($note['id']) -> description));
    }
}

putenv('DB_DATABASE=glommer_fetch_fixture_' . getmypid());

if (!TestDatabase::setUp()) {
    exit(1);
}

register_shutdown_function([TestDatabase::class, 'tearDown']);
$cases = new FederationFetchCases();
$failed = 0;
$passed = 0;

foreach (get_class_methods($cases) as $method) {
    if (!str_starts_with($method, 'test')) {
        continue;
    }

    SafeHTTPFetcher::$requests = [];
    SafeHTTPFetcher::$responses = [];
    DB::run('DELETE FROM `InboxFetches`');

    try {
        $cases -> $method();
        $passed++;
        echo 'PASS ' . $method . "\n";
    } catch (\Throwable $exception) {
        $failed++;
        echo 'FAIL ' . $method . ': ' . $exception -> getMessage() . "\n" . $exception -> getTraceAsString() . "\n";
    }
}

echo $failed === 0 ? 'All federation fixture cases passed (' . $passed . ").\n" : $failed . " federation fixture cases failed.\n";
exit($failed === 0 ? 0 : 1);
