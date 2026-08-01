<?php

declare(strict_types=1);

/**
 * Polls: who may answer, what an answer counts for, and what crosses the wire
 * in both directions.
 *
 * The inbound half carries the trap worth testing hardest - a vote is a Note
 * with an inReplyTo, so anything that reaches the reply path before recognising
 * it files an empty post in the thread for every answer anybody gives.
 */
class PollTest extends DatabaseTestCase
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

    private static function post(int $user_id, ?string $remote_uri = null): int
    {
        DB::run('
INSERT INTO `Posts` (`userId`, `description`, `descriptionDelta`, `remoteObjectURI`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, 'a question', json_encode([['insert' => "a question\n"]]), $remote_uri);

        return (int) mysqli_insert_id(DB::connection());
    }

    /** @return array{poll: Poll, optionIds: int[]} */
    private static function pollOn(int $post_id, bool $multiple = false, int $minutes = 60): array
    {
        $poll = Poll::create($post_id, ['Yes', 'No', 'Maybe'], $multiple, $minutes);

        $options = DB::rows('
SELECT `pollOptionId`
    FROM `PollOptions`
    WHERE `pollId` = ?
    ORDER BY `position`
', 'PollOption', 'i', (int) $poll -> pollId);

        return ['poll' => $poll, 'optionIds' => array_map(static fn (PollOption $o): int => (int) $o -> pollOptionId, $options)];
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

    public function testAVoteIsRecordedAndCounted(): void
    {
        $author = self::localUser();
        ['poll' => $poll, 'optionIds' => $options] = self::pollOn(self::post((int) $author -> userId));
        $voter = self::localUser();

        $this -> assertTrue(Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$options[0]]));
        $this -> assertSame(1, self::tallyFor($options[0]));
        $this -> assertSame(1, $poll -> voterCount());
    }

    public function testASecondVoteFromTheSamePersonIsRefused(): void
    {
        // A vote is final. Being able to change one after seeing the running
        // total would make the total the thing being voted on.
        $author = self::localUser();
        ['poll' => $poll, 'optionIds' => $options] = self::pollOn(self::post((int) $author -> userId));
        $voter = self::localUser();

        Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$options[0]]);

        $this -> assertFalse(Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$options[1]]));
        $this -> assertSame(0, self::tallyFor($options[1]));
    }

    public function testASingleAnswerPollRefusesTwoAnswers(): void
    {
        // The radio group enforces this in the page; this is the half that
        // holds when the request does not come from the page.
        $author = self::localUser();
        ['poll' => $poll, 'optionIds' => $options] = self::pollOn(self::post((int) $author -> userId));
        $voter = self::localUser();

        $this -> assertFalse(Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$options[0], $options[1]]));
        $this -> assertSame(0, self::tallyFor($options[0]));
    }

    public function testAMultipleAnswerPollTakesSeveral(): void
    {
        $author = self::localUser();
        ['poll' => $poll, 'optionIds' => $options] = self::pollOn(self::post((int) $author -> userId), true);
        $voter = self::localUser();

        $this -> assertTrue(Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$options[0], $options[2]]));
        $this -> assertSame(1, self::tallyFor($options[0]));
        $this -> assertSame(1, self::tallyFor($options[2]));

        // Still one person, however many boxes they ticked.
        $this -> assertSame(1, $poll -> voterCount());
    }

    public function testAnotherPollsOptionCannotBeUsedToAnswerThisOne(): void
    {
        $author = self::localUser();
        ['poll' => $poll] = self::pollOn(self::post((int) $author -> userId));
        ['optionIds' => $elsewhere] = self::pollOn(self::post((int) $author -> userId));
        $voter = self::localUser();

        $this -> assertFalse(Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$elsewhere[0]]));
        $this -> assertSame(0, self::tallyFor($elsewhere[0]));
    }

    public function testAClosedPollTakesNoMoreVotes(): void
    {
        $author = self::localUser();
        ['poll' => $poll, 'optionIds' => $options] = self::pollOn(self::post((int) $author -> userId));
        $voter = self::localUser();

        DB::run('
UPDATE `Polls`
    SET `endsAt` = ?
    WHERE `pollId` = ?
', 'si', gmdate('Y-m-d H:i:s', time() - 60), (int) $poll -> pollId);

        $this -> assertFalse(Poll::vote((int) $poll -> pollId, (int) $voter -> userId, [$options[0]]));
    }

    public function testOptionsThatCannotBeToldApartAreRefused(): void
    {
        // A vote names its option by text, so two options reading the same
        // could not be told apart when one came back.
        $this -> assertSame(['Yes', 'No'], Poll::cleanOptions(['Yes', ' ', 'No', 'yes', '']));
    }

    public function testAPollLeavesHereAsAQuestion(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);
        ['optionIds' => $options] = self::pollOn($post_id, false, 60);

        $voter = self::localUser();
        Poll::vote(Poll::forPost($post_id) -> pollId, (int) $voter -> userId, [$options[1]]);

        $post = Post::fromRowWithItems(DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id));

        $document = ActivityPubNote::document($post, $author);

        $this -> assertSame('Question', $document['type']);
        // oneOf, not anyOf: which key carries the choices is the only thing on
        // the wire that says whether several may be picked.
        $this -> assertTrue(isset($document['oneOf']));
        $this -> assertFalse(isset($document['anyOf']));
        $this -> assertSame(3, count($document['oneOf']));
        $this -> assertSame('No', $document['oneOf'][1]['name']);
        $this -> assertSame(1, $document['oneOf'][1]['replies']['totalItems']);
        $this -> assertSame(1, $document['votersCount']);
        $this -> assertTrue(isset($document['endTime']));
    }

    public function testAMultipleChoicePollLeavesHereAsAnyOf(): void
    {
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);
        self::pollOn($post_id, true);

        $post = Post::fromRowWithItems(DB::row('
SELECT *
    FROM `Posts`
    WHERE `postId` = ?
', 'Post', 'i', $post_id));

        $document = ActivityPubNote::document($post, $author);

        $this -> assertTrue(isset($document['anyOf']));
        $this -> assertFalse(isset($document['oneOf']));
    }

    public function testAnInboundQuestionArrivesAsAPollWithTheOriginsTallies(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Question',
                'id' => $object_uri,
                'content' => 'which one',
                'endTime' => gmdate('c', time() + 3600),
                'votersCount' => 7,
                'oneOf' => [
                    ['type' => 'Note', 'name' => 'First', 'replies' => ['type' => 'Collection', 'totalItems' => 5]],
                    ['type' => 'Note', 'name' => 'Second', 'replies' => ['type' => 'Collection', 'totalItems' => 2]],
                ],
            ],
        ], $actor_uri);

        $post = DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);

        $this -> assertNotNull($post);

        $poll = Poll::forPost((int) $post -> postId);

        $this -> assertNotNull($poll);
        // The origin's figure, because this server holds none of the votes
        // behind it and cannot count them itself.
        $this -> assertSame(7, $poll -> voterCount());

        $options = DB::rows('
SELECT *
    FROM `PollOptions`
    WHERE `pollId` = ?
    ORDER BY `position`
', 'PollOption', 'i', (int) $poll -> pollId);

        $this -> assertSame(2, count($options));
        $this -> assertSame('First', $options[0] -> title);
        $this -> assertSame(5, $options[0] -> voteCount());
    }

    public function testAnInboundVoteIsCountedAndIsNotFiledAsAReply(): void
    {
        // The whole reason vote detection runs before the reply path: a vote
        // and a reply are the same shape but for the missing content, and
        // taking one for the other puts an empty post in the thread.
        $author = self::localUser();
        $post_id = self::post((int) $author -> userId);
        self::pollOn($post_id);

        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        $voter = self::shadowUser($actor_uri);

        ActivityPubInbox::process([
            'type' => 'Create',
            'object' => [
                'type' => 'Note',
                'id' => 'https://remote.test/votes/' . bin2hex(random_bytes(6)),
                'name' => 'Maybe',
                'inReplyTo' => ActivityPubNote::uriFor(
                    DB::row('SELECT * FROM `Posts` WHERE `postId` = ?', 'Post', 'i', $post_id),
                    $author
                ),
                'to' => [ActivityPubActor::uriFor($author)],
            ],
        ], $actor_uri);

        $poll = Poll::forPost($post_id);

        $this -> assertTrue($poll -> hasVoted((int) $voter -> userId));

        $replies = DB::rows('
SELECT `postId`
    FROM `Posts`
    WHERE `parentId` = ?
', 'Post', 'i', $post_id);

        $this -> assertSame(0, count($replies), 'a vote must never become a post in the thread');
    }

    public function testAnUpdateReplacesARemotePollsTalliesButNotItsChoices(): void
    {
        $actor_uri = 'https://remote.test/users/' . bin2hex(random_bytes(6));
        self::shadowUser($actor_uri);
        $object_uri = 'https://remote.test/notes/' . bin2hex(random_bytes(6));

        $question = [
            'type' => 'Question',
            'id' => $object_uri,
            'content' => 'which one',
            'endTime' => gmdate('c', time() + 3600),
            'votersCount' => 1,
            'oneOf' => [
                ['type' => 'Note', 'name' => 'First', 'replies' => ['type' => 'Collection', 'totalItems' => 1]],
                ['type' => 'Note', 'name' => 'Second', 'replies' => ['type' => 'Collection', 'totalItems' => 0]],
            ],
        ];

        ActivityPubInbox::process(['type' => 'Create', 'object' => $question], $actor_uri);

        $question['votersCount'] = 9;
        $question['oneOf'][0]['replies']['totalItems'] = 4;
        $question['oneOf'][1]['replies']['totalItems'] = 5;
        // An origin that renamed a choice mid-poll would be changing what the
        // votes already counted were cast for, so this must not take.
        $question['oneOf'][1]['name'] = 'Renamed';

        ActivityPubInbox::process(['type' => 'Update', 'object' => $question], $actor_uri);

        $post = DB::row('
SELECT `postId`
    FROM `Posts`
    WHERE `remoteObjectURI` = ?
', 'Post', 's', $object_uri);
        $poll = Poll::forPost((int) $post -> postId);

        $this -> assertSame(9, $poll -> voterCount());

        $options = DB::rows('
SELECT *
    FROM `PollOptions`
    WHERE `pollId` = ?
    ORDER BY `position`
', 'PollOption', 'i', (int) $poll -> pollId);

        $this -> assertSame(4, $options[0] -> voteCount());
        $this -> assertSame('Second', $options[1] -> title);
        // Unchanged: the renamed choice matched nothing, so its count stands.
        $this -> assertSame(0, $options[1] -> voteCount());
    }
}
