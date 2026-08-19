<?php

declare(strict_types=1);

class DBTransactionTest extends DatabaseTestCase
{
    private function emailOf(int $user_id): string
    {
        return (string) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `email`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['email'];
    }

    public function testACompletedTransactionCommits(): void
    {
        $user_id = self::createUser();
        $email = 'committed-' . bin2hex(random_bytes(4)) . '@example.test';

        DB::transaction(static function () use ($user_id, $email): void {
            DB::run('
UPDATE `Users`
    SET `email` = ?
    WHERE `userId` = ?
', 'si', $email, $user_id);
        });

        $this -> assertSame($email, $this -> emailOf($user_id));
    }

    public function testAThrownTransactionRollsBack(): void
    {
        $user_id = self::createUser();
        $before = $this -> emailOf($user_id);

        try {
            DB::transaction(static function () use ($user_id): void {
                DB::run('
UPDATE `Users`
    SET `email` = ?
    WHERE `userId` = ?
', 'si', 'rolled-back-' . bin2hex(random_bytes(4)) . '@example.test', $user_id);

                throw new \RuntimeException('force rollback');
            });
        } catch (\RuntimeException $exception) {
            $this -> assertSame('force rollback', $exception -> getMessage());
        }

        $this -> assertSame($before, $this -> emailOf($user_id));
    }
}
