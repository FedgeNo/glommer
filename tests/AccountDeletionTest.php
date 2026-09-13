<?php

declare(strict_types=1);

class AccountDeletionTest extends DatabaseTestCase
{
    public function testDeletingAFriendAdjustsBothDirectionsWithoutCountingPendingRequests(): void
    {
        $deleted = self::createUser();
        $first = self::createUser();
        $second = self::createUser();
        $pending = self::createUser();

        foreach ([[$deleted, $first], [$second, $deleted]] as [$requester, $addressee]) {
            DB::run('INSERT INTO `Friendships` (`requesterId`, `addresseeId`, `status`) VALUES (?, ?, ?)',
                'iis', $requester, $addressee, 'accepted');
            User::incrementFriendCounts($requester, $addressee);
        }

        DB::run('INSERT INTO `Friendships` (`requesterId`, `addresseeId`, `status`) VALUES (?, ?, ?)',
            'iis', $deleted, $pending, 'pending');
        User::delete($deleted);

        foreach ([$first, $second, $pending] as $survivor) {
            $this -> assertSame(0, User::load($survivor) -> friendCount);
            $this -> assertSame([], User::load($survivor) -> friendIds());
        }
    }
}
