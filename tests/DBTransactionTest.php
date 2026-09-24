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

    public function testReadCommittedRefreshesReadsOnlyForItsTransaction(): void
    {
        $second_connection = mysqli_connect('localhost', 'root', '', Config::get('database'));
        $slug = '';
        $email = '';
        $hash = self::cheapHash('test');
        $inserted_ids = [];

        try {
            $insert = mysqli_prepare($second_connection, 'INSERT INTO `Users` (`slug`, `email`, `passwordHash`) VALUES (?, ?, ?)');
            mysqli_stmt_bind_param($insert, 'sss', $slug, $email, $hash);

            $insert_user = static function () use ($second_connection, $insert, &$slug, &$email, &$inserted_ids): void {
                $slug = 'isolation-' . bin2hex(random_bytes(8));
                $email = $slug . '@example.test';
                mysqli_stmt_execute($insert);
                $inserted_ids[] = (int) mysqli_insert_id($second_connection);
            };
            $user_count = static fn (): int => (int) DB::row('SELECT COUNT(*) AS `total` FROM `Users`', \stdClass::class) -> total;

            DB::transaction(function () use ($insert_user, $user_count): void {
                $before = $user_count();
                $insert_user();
                $this -> assertSame($before + 1, $user_count());
            }, read_committed: true);

            DB::transaction(function () use ($insert_user, $user_count): void {
                $before = $user_count();
                $insert_user();
                $this -> assertSame($before, $user_count());
            });
        } finally {
            foreach ($inserted_ids as $user_id) {
                DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user_id);
            }
            mysqli_close($second_connection);
        }
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
