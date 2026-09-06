<?php

declare(strict_types=1);

class ActivityPubResponseTest extends DatabaseTestCase
{
    private static function user(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

    public function testAnOrderedCollectionCarriesItsIdentityItemsAndCount(): void
    {
        $document = ActivityPubResponse::orderedCollection('https://local.invalid/followers', [
            4 => 'https://remote.invalid/users/one',
            9 => 'https://remote.invalid/users/two',
        ]);

        $this -> assertSame('https://www.w3.org/ns/activitystreams', $document['@context']);
        $this -> assertSame('https://local.invalid/followers', $document['id']);
        $this -> assertSame('OrderedCollection', $document['type']);
        $this -> assertSame(2, $document['totalItems']);
        $this -> assertSame([
            'https://remote.invalid/users/one',
            'https://remote.invalid/users/two',
        ], $document['orderedItems']);
    }

    public function testAStandaloneDocumentGetsTheActivityStreamsContext(): void
    {
        $document = ActivityPubResponse::standaloneDocument([
            'id' => 'https://local.invalid/posts/1',
            'type' => 'Note',
        ]);

        $this -> assertSame('https://www.w3.org/ns/activitystreams', $document['@context']);
        $this -> assertSame('https://local.invalid/posts/1', $document['id']);
    }

    public function testAStandaloneDocumentKeepsItsExistingContext(): void
    {
        $context = ['https://www.w3.org/ns/activitystreams', 'https://example.invalid/context'];
        $document = ActivityPubResponse::standaloneDocument([
            '@context' => $context,
            'type' => 'Person',
        ]);

        $this -> assertSame($context, $document['@context']);
    }

    public function testALocalMemberCanBePublishedAsAnActor(): void
    {
        $user = self::user();
        $found = ActivityPubResponse::localUser((string) $user -> slug);

        $this -> assertNotNull($found);
        $this -> assertSame((int) $user -> userId, (int) $found -> userId);
    }

    public function testAnUnknownMemberHasNoActor(): void
    {
        $this -> assertNull(ActivityPubResponse::localUser('missing-' . bin2hex(random_bytes(6))));
    }

    public function testABannedMemberHasNoActor(): void
    {
        $user = self::user();

        DB::run('
UPDATE `Users`
    SET `banned` = ?
    WHERE `userId` = ?
', 'ii', 1, (int) $user -> userId);

        $this -> assertNull(ActivityPubResponse::localUser((string) $user -> slug));
    }

    public function testAShadowAccountHasNoActorOnThisServer(): void
    {
        $unique = bin2hex(random_bytes(6));
        $slug = 'shadow-' . $unique . '@remote.invalid';

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `verified`)
    VALUES (?, ?, ?, ?, ?)
', 'ssssi', $slug, 'shadow-' . $unique . '@example.test', self::cheapHash('x'), 'https://remote.invalid/users/' . $unique, 1);

        $this -> assertNull(ActivityPubResponse::localUser($slug));
    }
}
