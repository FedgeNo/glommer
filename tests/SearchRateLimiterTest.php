<?php

declare(strict_types=1);

class SearchRateLimiterTest extends DatabaseTestCase
{
    public function testEachSearchScopeStopsAfterItsOwnBudget(): void
    {
        $user_id = self::createUser();

        foreach (['posts', 'users', 'friends', 'banned-users'] as $scope) {
            $this -> assertFalse(SearchRateLimiter::tooManyAttempts($scope, $user_id, 1), $scope . ' allowed its first search');
            $this -> assertTrue(SearchRateLimiter::tooManyAttempts($scope, $user_id, 1), $scope . ' refused its second search');
        }
    }

    public function testOneSearchDoesNotSpendAnotherSearchesBudget(): void
    {
        $user_id = self::createUser();

        $this -> assertFalse(SearchRateLimiter::tooManyAttempts('posts', $user_id, 1));
        $this -> assertFalse(SearchRateLimiter::tooManyAttempts('users', $user_id, 1));
    }
}
