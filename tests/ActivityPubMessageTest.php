<?php

declare(strict_types=1);

/**
 * Direct messages crossing servers. The load-bearing decision is telling a DM
 * from a post: a Note is private because it omits the public audience, not
 * because it mentions somebody - a public post can mention somebody too, and
 * treating that as a DM would file a stranger's public writing into an inbox.
 */
class ActivityPubMessageTest extends DatabaseTestCase
{
    private static function localUser(): User
    {
        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', self::createUser());
    }

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

    private static function note(User $to, string $body = 'hello', ?string $uri = null): array
    {
        return [
            'id' => $uri ?? 'https://remote.invalid/notes/' . bin2hex(random_bytes(6)),
            'type' => 'Note',
            'content' => '<p>' . $body . '</p>',
            'to' => [ActivityPubActor::uriFor($to)],
        ];
    }

    private static function messageCount(int $sender_id, int $recipient_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
', 'PostCountData', 'ii', $sender_id, $recipient_id);

        return (int) $row -> total;
    }

    // ----------------------------------------------------------------
    // Telling a message from a post
    // ----------------------------------------------------------------

    public function testANoteWithNoPublicAudienceIsADirectMessage(): void
    {
        $member = self::localUser();

        $this -> assertTrue(ActivityPubMessage::isDirect(self::note($member), []));
    }

    public function testAPublicNoteIsNotADirectMessageEvenWhenItNamesSomeone(): void
    {
        // The trap: a public post mentioning a member addresses them too.
        $member = self::localUser();
        $note = self::note($member);
        $note['to'][] = ActivityPubActor::PUBLIC_AUDIENCE;

        $this -> assertFalse(ActivityPubMessage::isDirect($note, []));
    }

    public function testANoteToSomebodysFollowersIsNotADirectMessage(): void
    {
        $member = self::localUser();
        $note = self::note($member);
        $note['cc'] = ['https://remote.invalid/users/someone/followers'];

        $this -> assertFalse(ActivityPubMessage::isDirect($note, []));
    }

    public function testAnAudienceOnTheActivityCountsToo(): void
    {
        // Some servers address the Create rather than the Note.
        $member = self::localUser();
        $note = self::note($member);
        unset($note['to']);

        $this -> assertFalse(ActivityPubMessage::isDirect($note, ['to' => [ActivityPubActor::PUBLIC_AUDIENCE]]));
    }

    public function testANoteAddressedToNobodyIsNotTreatedAsPrivate(): void
    {
        $this -> assertFalse(ActivityPubMessage::isDirect(['type' => 'Note'], []));
    }

    // ----------------------------------------------------------------
    // Receiving
    // ----------------------------------------------------------------

    public function testAMessageFromElsewhereLandsInTheThread(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubMessage::received(self::note($member, 'hi there'), [], $them);

        $this -> assertSame(1, self::messageCount((int) $them -> userId, (int) $member -> userId));
    }

    public function testTheSameMessageTwiceIsStoredOnce(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();
        $note = self::note($member);

        ActivityPubMessage::received($note, [], $them);
        ActivityPubMessage::received($note, [], $them);

        $this -> assertSame(1, self::messageCount((int) $them -> userId, (int) $member -> userId));
    }

    public function testTheBodyArrivesAsTextNotMarkup(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubMessage::received(self::note($member, 'first</p><p>second'), [], $them);

        $row = DB::row('
SELECT `body`
    FROM `Messages`
    WHERE `senderId` = ? AND `recipientId` = ?
', 'Message', 'ii', (int) $them -> userId, (int) $member -> userId);

        $this -> assertFalse(str_contains((string) $row -> body, '<'));
        $this -> assertTrue(str_contains((string) $row -> body, 'first'));
        $this -> assertTrue(str_contains((string) $row -> body, 'second'));
    }

    public function testAMessageForNobodyHereIsDropped(): void
    {
        $them = self::remoteUser();
        $note = self::note(self::localUser());
        $note['to'] = ['https://elsewhere.invalid/users/someone'];

        ActivityPubMessage::received($note, [], $them);

        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `Messages`
    WHERE `senderId` = ?
', 'PostCountData', 'i', (int) $them -> userId);

        $this -> assertSame(0, (int) $row -> total);
    }

    public function testABlockStopsAFederatedMessageTheSameAsALocalOne(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        Block::create((int) $member -> userId, (int) $them -> userId);

        ActivityPubMessage::received(self::note($member), [], $them);

        $this -> assertSame(0, self::messageCount((int) $them -> userId, (int) $member -> userId));
    }

    public function testABannedSenderIsIgnored(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        DB::run('
UPDATE `Users`
    SET `banned` = 1
    WHERE `userId` = ?
', 'i', (int) $them -> userId);

        $banned = DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) $them -> userId);

        ActivityPubMessage::received(self::note($member), [], $banned);

        $this -> assertSame(0, self::messageCount((int) $them -> userId, (int) $member -> userId));
    }

    public function testAnEmptyMessageIsNotStored(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubMessage::received(self::note($member, ''), [], $them);

        $this -> assertSame(0, self::messageCount((int) $them -> userId, (int) $member -> userId));
    }

    // ----------------------------------------------------------------
    // Sending
    // ----------------------------------------------------------------

    public function testAMessageToARemoteAccountIsQueued(): void
    {
        $member = self::localUser();
        $them = self::remoteUser();

        $before = FediverseDelivery::pendingCount();
        ActivityPubMessage::publish(1, $member, $them, 'hello');

        $this -> assertSame($before + 1, FediverseDelivery::pendingCount());
    }

    public function testAnOutboundMessageIsAddressedToTheRecipientAlone(): void
    {
        // The omission of the public collection is the only thing marking it
        // private, so it has to actually be omitted.
        $member = self::localUser();
        $them = self::remoteUser();

        ActivityPubMessage::publish(1, $member, $them, 'hello');

        $row = DB::row('
SELECT *
    FROM `FediverseDeliveries`
    ORDER BY `deliveryId` DESC
    LIMIT 1
', 'FediverseDeliveryData');

        $activity = json_decode((string) $row -> activity, true);

        $this -> assertSame([$them -> remoteActorURI], $activity['object']['to']);
        $this -> assertFalse(in_array(ActivityPubActor::PUBLIC_AUDIENCE, $activity['object']['to'], true));
        $this -> assertFalse(isset($activity['object']['cc']));
    }

    public function testAMessageToALocalMemberIsNotFederated(): void
    {
        $member = self::localUser();
        $other = self::localUser();

        $before = FediverseDelivery::pendingCount();
        ActivityPubMessage::publish(1, $member, $other, 'hello');

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }
}
