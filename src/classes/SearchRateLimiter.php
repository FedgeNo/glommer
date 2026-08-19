<?php

declare(strict_types=1);

class SearchRateLimiter
{
    private const SCOPES = ['posts', 'users', 'friends', 'banned-users'];

    public static function tooManyAttempts(string $scope, int $user_id, int $max_attempts = 60): bool
    {
        if (!in_array($scope, self::SCOPES, true)) {
            throw new \InvalidArgumentException('Unknown search rate-limit scope: ' . $scope);
        }

        $rate_key = 'search-' . $scope . ':' . $user_id;

        if (RateLimiter::tooManyAttempts($rate_key, $max_attempts, 60)) {
            return true;
        }

        RateLimiter::recordAttempt($rate_key);

        return false;
    }
}
