<?php

declare(strict_types=1);

/**
 * Being told when somebody on another server does something to your post.
 *
 * Everything a member here can do already rings a bell, and none of the
 * inbound equivalents did. That failure is invisible from the inside: the reply
 * is in the thread, the like is in the count, the boost is in the tally, and
 * the only thing missing is the one thing that would have told anybody it
 * happened.
 */
class FediverseNoticeTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

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

    private static function post(int $user_id): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`)
    VALUES (?, ?, ?)
', 'iss', $user_id, 'something', json_encode([['insert' => "something\n"]]));

        return (int) mysqli_insert_id(DB::connection());
    }

    /** @return string[] the types this member was notified of, by this actor */
    private static function noticesFor(int $user_id, int $actor_id): array
    {
        $rows = DB::rows('
SELECT `type`
    FROM `Notifications`
    WHERE `userId` = ? AND `actorId` = ?
    ORDER BY `notificationId`
', 'Notification', 'ii', $user_id, $actor_id);

        return array_map(static fn (Notification $row): string => (string) $row -> type, $rows);
    }

    public function testALikeFromAnotherServerIsAnnounced(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Like',
            'object' => ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
        ], $actor_uri);

        $this -> assertSame(['like'], self::noticesFor((int) $author -> userId, (int) $actor -> userId));
    }

    public function testABoostFromAnotherServerIsAnnounced(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Announce',
            'id' => 'https://remote.test/activities/' . bin2hex(random_bytes(6)),
            'object' => ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
        ], $actor_uri);

        $this -> assertSame(['repost'], self::noticesFor((int) $author -> userId, (int) $actor -> userId));
    }

    public function testAReplyFromAnotherServerIsAnnounced(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => 'https://remote.test/notes/' . bin2hex(random_bytes(6)),
                'attributedTo' => $actor_uri,
                'content' => 'answering that',
                'inReplyTo' => ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
                'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
            ],
        ], $actor_uri);

        $this -> assertSame(['reply'], self::noticesFor((int) $author -> userId, (int) $actor -> userId));
    }

    /**
     * A reply tags whoever it answers as a Mention too, which is how the
     * network addresses one - so the same post must not arrive as two pieces
     * of news about the same event.
     */
    public function testAReplyThatAlsoTagsTheAuthorIsOnePieceOfNews(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => 'https://remote.test/notes/' . bin2hex(random_bytes(6)),
                'attributedTo' => $actor_uri,
                'content' => 'answering that',
                'inReplyTo' => ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
                'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
                'tag' => [[
                    'type' => 'Mention',
                    'name' => '@' . $author -> slug,
                    'href' => ServerURL::absolute('/users/' . $author -> slug . '/'),
                ]],
            ],
        ], $actor_uri);

        $this -> assertSame(['reply'], self::noticesFor((int) $author -> userId, (int) $actor -> userId));
    }

    /** Being named in a post that answers nothing is a mention on its own. */
    public function testAMentionFromAnotherServerIsAnnounced(): void
    {
        $named = self::localUser();

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => 'https://remote.test/notes/' . bin2hex(random_bytes(6)),
                'attributedTo' => $actor_uri,
                'content' => 'talking about someone',
                'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
                'tag' => [[
                    'type' => 'Mention',
                    'name' => '@' . $named -> slug,
                    'href' => ServerURL::absolute('/users/' . $named -> slug . '/'),
                ]],
            ],
        ], $actor_uri);

        $this -> assertSame(['mention'], self::noticesFor((int) $named -> userId, (int) $actor -> userId));
    }

    /** A Mention naming somebody on another server is not news for anybody here. */
    public function testAMentionOfARemoteAccountTellsNobodyHere(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);
        $other = self::shadowUser('https://elsewhere.test/users/' . bin2hex(random_bytes(6)));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => 'https://remote.test/notes/' . bin2hex(random_bytes(6)),
                'attributedTo' => $actor_uri,
                'content' => 'talking about someone elsewhere',
                'to' => [ActivityPubActor::PUBLIC_AUDIENCE],
                'tag' => [[
                    'type' => 'Mention',
                    'name' => '@somebody@elsewhere.test',
                    'href' => 'https://elsewhere.test/users/somebody',
                ]],
            ],
        ], $actor_uri);

        $this -> assertSame([], self::noticesFor((int) $other -> userId, (int) $actor -> userId));
    }

    public function testAFollowFromAnotherServerIsAnnounced(): void
    {
        $followed = self::localUser();

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Follow',
            'id' => 'https://remote.test/follows/' . bin2hex(random_bytes(6)),
            'object' => ActivityPubActor::uriFor($followed),
        ], $actor_uri);

        $this -> assertSame(['follow'], self::noticesFor((int) $followed -> userId, (int) $actor -> userId));
    }

    /**
     * A block someone can reach around from another server is not a block.
     * Notification::create has no opinion on blocks, so this is the guard.
     */
    public function testABlockedActorRingsNobodysBell(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);

        DB::run('
INSERT INTO `Blocks` (`blockerId`, `blockedId`)
    VALUES (?, ?)
', 'ii', (int) $author -> userId, (int) $actor -> userId);

        ActivityPubInbox::process([
            'type' => 'Like',
            'object' => ServerURL::absolute('/users/' . $author -> slug . '/' . $post_id),
        ], $actor_uri);

        $this -> assertSame([], self::noticesFor((int) $author -> userId, (int) $actor -> userId));
    }

    /** A like on a post that came from elsewhere has no author here to tell. */
    public function testALikeOnARemotePostTellsNobody(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $actor = self::shadowUser($actor_uri);
        $remote_author = self::shadowUser('https://elsewhere.test/users/' . bin2hex(random_bytes(6)));

        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', (int) $remote_author -> userId, 'theirs', json_encode([['insert' => "theirs\n"]]), 'https://elsewhere.test/notes/' . bin2hex(random_bytes(6)));

        ActivityPubInbox::process([
            'type' => 'Like',
            'object' => 'https://elsewhere.test/notes/nothing-of-ours',
        ], $actor_uri);

        $this -> assertSame([], self::noticesFor((int) $remote_author -> userId, (int) $actor -> userId));
    }
}
