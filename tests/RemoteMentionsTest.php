<?php

declare(strict_types=1);

class RemoteMentionsTest extends DatabaseTestCase
{
    private static function remoteUser(string $actor_uri, string $slug): User
    {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?)
', 'ssssssi', $slug, 'mention-' . $unique . '@example.test', self::cheapHash('x'), $actor_uri, 'not-a-real-key', $actor_uri . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    private static function mention(string $name, string $actor_uri): array
    {
        return [
            'type' => 'Mention',
            'name' => $name,
            'href' => $actor_uri,
        ];
    }

    private static function profileURL(User $user): string
    {
        return ServerURL::absolute('/users/' . rawurlencode((string) $user -> slug) . '/');
    }

    public function testARemoteMentionResolvesInBothFullAndBareForms(): void
    {
        $actor_uri = 'https://mentions.invalid/users/alice-' . bin2hex(random_bytes(4));
        $user = self::remoteUser($actor_uri, 'alice@mentions.invalid');

        $profiles = RemoteMentions::localProfiles([
            'tag' => [self::mention('@Alice@Mentions.Invalid', $actor_uri)],
        ]);

        $this -> assertSame(self::profileURL($user), $profiles['alice@mentions.invalid']);
        $this -> assertSame(self::profileURL($user), $profiles['alice']);
    }

    public function testTheFirstAccountNamedWithABareHandleWinsItsAmbiguity(): void
    {
        $first_actor_uri = 'https://one.invalid/users/sam-' . bin2hex(random_bytes(4));
        $second_actor_uri = 'https://two.invalid/users/sam-' . bin2hex(random_bytes(4));
        $first = self::remoteUser($first_actor_uri, 'sam@one.invalid');
        $second = self::remoteUser($second_actor_uri, 'sam@two.invalid');

        $profiles = RemoteMentions::localProfiles([
            'tag' => [
                self::mention('@sam@one.invalid', $first_actor_uri),
                self::mention('@sam@two.invalid', $second_actor_uri),
            ],
        ]);

        $this -> assertSame(self::profileURL($first), $profiles['sam']);
        $this -> assertSame(self::profileURL($first), $profiles['sam@one.invalid']);
        $this -> assertSame(self::profileURL($second), $profiles['sam@two.invalid']);
    }

    public function testMalformedAndNonMentionTagsNameNobody(): void
    {
        $this -> assertSame([], RemoteMentions::localProfiles([
            'tag' => [
                'not an object',
                ['type' => 'Hashtag', 'name' => '#topic', 'href' => 'https://remote.invalid/tags/topic'],
                ['type' => 'Mention', 'name' => '@nobody', 'href' => ''],
                ['type' => 'Mention', 'href' => 'https://remote.invalid/users/nobody'],
            ],
        ]));
    }

    public function testAReferenceToOneOfOurOwnActorsIsNotImportedAsRemote(): void
    {
        $user = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());

        $this -> assertSame([], RemoteMentions::localProfiles([
            'tag' => [self::mention('@' . $user -> slug, ActivityPubActor::uriFor($user))],
        ]));
    }
}
