<?php

declare(strict_types=1);

class RememberTokenTest extends DatabaseTestCase
{
    private function createToken(int $user_id): string
    {
        $create = new \ReflectionMethod(RememberToken::class, 'create');
        $create -> setAccessible(true);

        return (string) $create -> invoke(null, $user_id);
    }

    private function tokenForSelector(string $selector): RememberTokenData
    {
        $token = DB::row('
SELECT `tokenId`, `userId`, `validatorHash`, `createdAt`, `consumedAt`
    FROM `RememberTokens`
    WHERE `selector` = ?
', 'RememberTokenData', 's', $selector);

        $this -> assertNotNull($token);

        return $token;
    }

    private function consumeAndReplace(RememberTokenData $token): ?string
    {
        $consume = new \ReflectionMethod(RememberToken::class, 'consumeAndReplace');
        $consume -> setAccessible(true);

        $replacement = $consume -> invoke(null, $token);

        return is_string($replacement) ? $replacement : null;
    }

    public function testRotationConsumesTheOldTokenAndCreatesOneSuccessor(): void
    {
        $user_id = self::createUser();
        [$selector] = explode(':', $this -> createToken($user_id), 2);
        $token = $this -> tokenForSelector($selector);

        $replacement = $this -> consumeAndReplace($token);

        $this -> assertNotNull($replacement);
        $this -> assertNotNull($this -> tokenForSelector($selector) -> consumedAt, 'the old selector remains as a consumed tombstone');

        $counts = mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT
    SUM(`consumedAt` IS NULL) AS `active`,
    SUM(`consumedAt` IS NOT NULL) AS `consumed`
    FROM `RememberTokens`
    WHERE `userId` = ?
', 'i', $user_id)));

        $this -> assertSame('1', (string) $counts['active']);
        $this -> assertSame('1', (string) $counts['consumed']);
    }

    public function testAConsumedTokenCannotCreateAnotherSuccessor(): void
    {
        $user_id = self::createUser();
        [$selector] = explode(':', $this -> createToken($user_id), 2);
        $token = $this -> tokenForSelector($selector);

        $this -> assertNotNull($this -> consumeAndReplace($token));
        $this -> assertNull($this -> consumeAndReplace($token));

        $active = mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT COUNT(*) AS `total`
    FROM `RememberTokens`
    WHERE `userId` = ? AND `consumedAt` IS NULL
', 'i', $user_id)));

        $this -> assertSame('1', (string) $active['total'], 'reuse did not mint a second active token');
    }

    public function testEnablingTwoFactorRevokesEveryRememberedDevice(): void
    {
        $user_id = self::createUser();
        $this -> createToken($user_id);
        $this -> createToken($user_id);

        TwoFactor::setEnabled($user_id, true);

        $remaining = DB::row('
SELECT COUNT(*) AS `total`
    FROM `RememberTokens`
    WHERE `userId` = ?
', 'PostCountData', 'i', $user_id);

        $this -> assertSame(0, (int) $remaining -> total);
    }
}
