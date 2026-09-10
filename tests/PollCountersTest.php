<?php

declare(strict_types=1);

class PollCountersTest extends DatabaseTestCase
{
    private static function poll(int $author): array
    {
        DB::run('INSERT INTO `Posts` (`userId`) VALUES (?)', 'i', $author);
        $poll = Poll::create((int) mysqli_insert_id(DB::connection()), ['One', 'Two'], true, 60);
        $options = DB::rows('SELECT * FROM `PollOptions` WHERE `pollId` = ? ORDER BY `position`', 'PollOption', 'i', $poll -> pollId);

        return [$poll, array_map(static fn (PollOption $option): int => $option -> pollOptionId, $options)];
    }

    private static function optionCounts(int $poll): array
    {
        return array_map(static fn (PollOption $option): int => $option -> localVoteCount,
            (new PollOptionList(['pollId' => $poll])) -> items);
    }

    public function testDeletingAVoterSubtractsEachChoiceButOnlyOneVoter(): void
    {
        $author = self::createUser();
        $leaving = self::createUser();
        $staying = self::createUser();

        try {
            [$poll, $options] = self::poll($author);
            Poll::vote($poll -> pollId, $leaving, $options);
            Poll::vote($poll -> pollId, $staying, [$options[0]]);
            $this -> assertSame([2, 1], self::optionCounts($poll -> pollId));
            $this -> assertSame(2, $poll -> voterCount());
            User::delete($leaving);
            User::delete($leaving);
            $this -> assertSame([1, 0], self::optionCounts($poll -> pollId));
            $this -> assertSame(1, $poll -> voterCount());
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?, ?)', 'iii', $author, $leaving, $staying);
        }
    }

    public function testAWriteFailureRollsBackAllChoicesAndTheirTotals(): void
    {
        $author = self::createUser();
        $voter = self::createUser();

        try {
            [$poll, $options] = self::poll($author);
            DB::run('UPDATE `PollOptions` SET `localVoteCount` = 4294967295 WHERE `pollOptionId` = ?', 'i', $options[1]);
            $failed = false;

            try {
                Poll::vote($poll -> pollId, $voter, $options);
            } catch (\mysqli_sql_exception $exception) {
                $failed = true;
            }

            $this -> assertTrue($failed);
            $this -> assertFalse($poll -> hasVoted($voter));
            $this -> assertSame(0, $poll -> voterCount());
            $this -> assertSame([0, 4294967295], self::optionCounts($poll -> pollId));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $author, $voter);
        }
    }

    public function testBackfillRepairsLocalTotalsAndPreservesTheOriginsNumbers(): void
    {
        $author = self::createUser();
        $voter = self::createUser();

        try {
            [$poll, $options] = self::poll($author);
            Poll::vote($poll -> pollId, $voter, $options);
            DB::run('UPDATE `Polls` SET `localVoterCount` = 99, `remoteVotersCount` = 77 WHERE `pollId` = ?', 'i', $poll -> pollId);
            DB::run('UPDATE `PollOptions` SET `localVoteCount` = 99, `remoteVoteCount` = 42 WHERE `pollId` = ?', 'i', $poll -> pollId);

            for ($pass = 0; $pass < 2; $pass++) {
                SchemaInstaller::runMaintenance(DB::connection());
                $loaded = Poll::forPost($poll -> postId);
                $this -> assertSame(1, $loaded -> localVoterCount);
                $this -> assertSame(77, $loaded -> voterCount());
                $loaded_options = (new PollOptionList(['pollId' => $poll -> pollId])) -> items;
                $this -> assertSame([1, 1], self::optionCounts($poll -> pollId));
                $this -> assertSame(42, $loaded_options[0] -> voteCount());
            }

            User::delete($voter);
            $this -> assertSame([0, 0], self::optionCounts($poll -> pollId));
            $this -> assertSame(77, Poll::forPost($poll -> postId) -> voterCount());
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $author, $voter);
        }
    }
}
