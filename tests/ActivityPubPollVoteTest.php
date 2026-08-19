<?php

declare(strict_types=1);

class ActivityPubPollVoteTest extends DatabaseTestCase
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
        $unique = bin2hex(random_bytes(6));
        $actor_uri = 'https://polls.invalid/users/' . $unique;

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `title`, `remoteActorURI`, `remoteActorPublicKeyPem`, `remoteActorInboxURL`, `verified`)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?)
', 'sssssssi', 'poll-' . $unique . '@polls.invalid', 'poll-' . $unique . '@example.test', self::cheapHash('x'), 'Remote Pollster', $actor_uri, 'not-a-real-key', $actor_uri . '/inbox', 1);

        return DB::row('
SELECT *
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    private static function post(User $author, ?string $remote_object_uri = null): Post
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', (int) $author -> userId, 'a federated poll', json_encode([['insert' => "a federated poll\n"]]), $remote_object_uri);

        return DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) mysqli_insert_id(DB::connection()));
    }

    /** @return array{poll: Poll, options: PollOption[], target: string} */
    private static function localPoll(bool $multiple = false): array
    {
        $author = self::localUser();
        $post = self::post($author);
        $poll = Poll::create((int) $post -> postId, ['Yes', 'No', 'Maybe'], $multiple, 60);

        return [
            'poll' => $poll,
            'options' => self::optionsFor((int) $poll -> pollId),
            'target' => ActivityPubNote::uriFor($post, $author),
        ];
    }

    /** @return array{poll: Poll, options: PollOption[], author: User} */
    private static function remotePoll(): array
    {
        $author = self::remoteUser();
        $post = self::post($author, 'https://polls.invalid/objects/' . bin2hex(random_bytes(6)));
        $poll = Poll::create((int) $post -> postId, ['Yes', 'No', 'Maybe'], true, 60);

        return [
            'poll' => $poll,
            'options' => self::optionsFor((int) $poll -> pollId),
            'author' => $author,
        ];
    }

    /** @return PollOption[] */
    private static function optionsFor(int $poll_id): array
    {
        return DB::rows('
SELECT *
    FROM `PollOptions`
    WHERE `pollId` = ?
    ORDER BY `position`
', 'PollOption', 'i', $poll_id);
    }

    private static function vote(string $target, string $name): array
    {
        return [
            'type' => 'Note',
            'name' => $name,
            'inReplyTo' => $target,
        ];
    }

    private static function tallyFor(int $option_id): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `PollVotes`
    WHERE `pollOptionId` = ?
', 'PostCountData', 'i', $option_id);

        return $row === null ? 0 : (int) $row -> total;
    }

    /** @return FediverseDeliveryData[] */
    private static function deliveriesFor(string $inbox_url): array
    {
        return DB::rows('
SELECT *
    FROM `FediverseDeliveries`
    WHERE `inboxURL` = ?
    ORDER BY `deliveryId`
', 'FediverseDeliveryData', 's', $inbox_url);
    }

    private static function clearDeliveries(string $inbox_url): void
    {
        DB::run('
DELETE FROM `FediverseDeliveries`
    WHERE `inboxURL` = ?
', 's', $inbox_url);
    }

    public function testAVoteNeedsANameAParentAndNoContent(): void
    {
        $this -> assertTrue(ActivityPubPollVote::isVote([
            'name' => 'Yes',
            'inReplyTo' => 'https://remote.invalid/polls/1',
        ]));
        $this -> assertFalse(ActivityPubPollVote::isVote(['inReplyTo' => 'https://remote.invalid/polls/1']));
        $this -> assertFalse(ActivityPubPollVote::isVote(['name' => 'Yes']));
        $this -> assertFalse(ActivityPubPollVote::isVote([
            'name' => 'Yes',
            'inReplyTo' => 'https://remote.invalid/polls/1',
            'content' => 'This is a titled reply.',
        ]));
    }

    public function testAnUnknownOptionIsIgnored(): void
    {
        ['options' => $options, 'target' => $target] = self::localPoll();
        $voter = self::remoteUser();

        ActivityPubPollVote::received(self::vote($target, 'Not offered'), $voter);

        $this -> assertSame(0, self::tallyFor((int) $options[0] -> pollOptionId));
    }

    public function testAClosedPollIgnoresAnInboundVote(): void
    {
        ['poll' => $poll, 'options' => $options, 'target' => $target] = self::localPoll();
        $voter = self::remoteUser();

        DB::run('
UPDATE `Polls`
    SET `endsAt` = ?
    WHERE `pollId` = ?
', 'si', gmdate('Y-m-d H:i:s', time() - 60), (int) $poll -> pollId);

        ActivityPubPollVote::received(self::vote($target, 'Yes'), $voter);

        $this -> assertSame(0, self::tallyFor((int) $options[0] -> pollOptionId));
    }

    public function testASingleChoicePollKeepsOnlyTheFirstInboundVote(): void
    {
        ['options' => $options, 'target' => $target] = self::localPoll();
        $voter = self::remoteUser();

        ActivityPubPollVote::received(self::vote($target, 'Yes'), $voter);
        ActivityPubPollVote::received(self::vote($target, 'No'), $voter);

        $this -> assertSame(1, self::tallyFor((int) $options[0] -> pollOptionId));
        $this -> assertSame(0, self::tallyFor((int) $options[1] -> pollOptionId));
    }

    public function testAMultipleChoicePollAcceptsSeparateInboundVotes(): void
    {
        ['options' => $options, 'target' => $target] = self::localPoll(true);
        $voter = self::remoteUser();

        ActivityPubPollVote::received(self::vote($target, 'Yes'), $voter);
        ActivityPubPollVote::received(self::vote($target, 'Maybe'), $voter);

        $this -> assertSame(1, self::tallyFor((int) $options[0] -> pollOptionId));
        $this -> assertSame(1, self::tallyFor((int) $options[2] -> pollOptionId));
    }

    public function testAVoteOnARemotePollIsNotCountedHere(): void
    {
        ['poll' => $poll, 'options' => $options] = self::remotePoll();
        $voter = self::remoteUser();
        $post = DB::row('
SELECT `remoteObjectURI`
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', (int) $poll -> postId);

        ActivityPubPollVote::received(self::vote((string) $post -> remoteObjectURI, 'Yes'), $voter);

        $this -> assertSame(0, self::tallyFor((int) $options[0] -> pollOptionId));
    }

    public function testAnOutboundVoteNamesThePollTheOptionAndTheVoter(): void
    {
        ['poll' => $poll, 'options' => $options, 'author' => $author] = self::remotePoll();
        $voter = self::localUser();
        $inbox_url = (string) $author -> remoteActorInboxURL;

        ActivityPubPollVote::publish($poll, $voter, [(int) $options[1] -> pollOptionId]);

        $rows = self::deliveriesFor($inbox_url);
        self::clearDeliveries($inbox_url);
        $this -> assertSame(1, count($rows));

        $activity = json_decode((string) $rows[0] -> activity, true);
        $voter_uri = ActivityPubActor::uriFor($voter);

        $this -> assertSame((int) $voter -> userId, (int) $rows[0] -> actorUserId);
        $this -> assertSame('Create', $activity['type']);
        $this -> assertSame($voter_uri, $activity['actor']);
        $this -> assertSame('No', $activity['object']['name']);
        $this -> assertSame($voter_uri . '#poll-votes/' . $options[1] -> pollOptionId, $activity['object']['id']);
        $this -> assertSame([$author -> remoteActorURI], $activity['object']['to']);
    }

    public function testOutboundChoicesAreDeduplicatedAndQueuedInPollOrder(): void
    {
        ['poll' => $poll, 'options' => $options, 'author' => $author] = self::remotePoll();
        $voter = self::localUser();
        $inbox_url = (string) $author -> remoteActorInboxURL;

        ActivityPubPollVote::publish($poll, $voter, [
            (int) $options[2] -> pollOptionId,
            (int) $options[0] -> pollOptionId,
            (int) $options[2] -> pollOptionId,
            PHP_INT_MAX,
        ]);

        $rows = self::deliveriesFor($inbox_url);
        self::clearDeliveries($inbox_url);
        $names = array_map(
            static fn (FediverseDeliveryData $row): string => (string) json_decode((string) $row -> activity, true)['object']['name'],
            $rows
        );

        $this -> assertSame(['Yes', 'Maybe'], $names);
    }

    public function testALocalPollQueuesNoFederatedVote(): void
    {
        ['poll' => $poll, 'options' => $options] = self::localPoll();
        $voter = self::localUser();
        $before = FediverseDelivery::pendingCount();

        ActivityPubPollVote::publish($poll, $voter, [(int) $options[0] -> pollOptionId]);

        $this -> assertSame($before, FediverseDelivery::pendingCount());
    }

    public function testARemoteAccountCannotPublishAVoteAsOneOfOurMembers(): void
    {
        ['poll' => $poll, 'options' => $options, 'author' => $author] = self::remotePoll();
        $voter = self::remoteUser();
        $inbox_url = (string) $author -> remoteActorInboxURL;

        ActivityPubPollVote::publish($poll, $voter, [(int) $options[0] -> pollOptionId]);

        $this -> assertSame([], self::deliveriesFor($inbox_url));
    }
}
