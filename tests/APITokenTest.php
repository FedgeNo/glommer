<?php

declare(strict_types=1);

class APITokenTest extends DatabaseTestCase
{
    public function testRotationRevokesTheOldTokenAndKeepsTheNewOneReadable(): void
    {
        $user_id = self::createUser();
        DB::run('
UPDATE `Users`
    SET `verified` = 1
    WHERE `userId` = ?
', 'i', $user_id);

        $first = APIToken::rotate($user_id);
        $this -> assertSame($first, APIToken::current($user_id));
        $this -> assertSame($user_id, (int) APIToken::userForBearer('Bearer ' . $first) -> userId);

        $second = APIToken::rotate($user_id);
        $this -> assertTrue($first !== $second);
        $this -> assertSame($second, APIToken::current($user_id));
        $this -> assertNull(APIToken::userForBearer('Bearer ' . $first));
        $this -> assertSame($user_id, (int) APIToken::userForBearer('Bearer ' . $second) -> userId);
        $this -> assertNull(APIToken::userForBearer('Bearer ' . $second . 'x'));
    }

    public function testUnverifiedAndBannedAccountsCannotUseTheirToken(): void
    {
        $user_id = self::createUser();
        $token = APIToken::rotate($user_id);

        $this -> assertNull(APIToken::userForBearer('Bearer ' . $token));

        DB::run('
UPDATE `Users`
    SET `verified` = 1, `banned` = 1
    WHERE `userId` = ?
', 'i', $user_id);

        $this -> assertNull(APIToken::userForBearer('Bearer ' . $token));
    }
}
