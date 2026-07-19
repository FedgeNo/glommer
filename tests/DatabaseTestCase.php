<?php

declare(strict_types=1);

/**
 * Base for a test that needs real rows in the database - extend this instead
 * of TestCase. bin/run-tests.php only runs these when it's itself running as
 * root (see TestDatabase): building/dropping the throwaway database needs
 * the DB server's root account, so an unprivileged run skips them outright
 * rather than failing.
 *
 * Every DB test in a run shares the one seeded database TestDatabase builds
 * once and drops once - no per-test isolation - so tests that read data
 * written by an earlier test in the same run must create their own users/
 * messages/etc. rather than assuming a clean table.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static function createUser(): int
    {
        $unique = bin2hex(random_bytes(6));

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`)
    VALUES (?, ?, ?)
', 'sss', 'test-' . $unique, 'test-' . $unique . '@example.test', password_hash($unique, PASSWORD_DEFAULT));

        return (int) mysqli_insert_id(DB::connection());
    }

    protected static function createMessage(int $sender_id, int $recipient_id): int
    {
        DB::run('
INSERT INTO `Messages` (`senderId`, `recipientId`, `body`)
    VALUES (?, ?, ?)
', 'iis', $sender_id, $recipient_id, 'test message ' . bin2hex(random_bytes(4)));

        return (int) mysqli_insert_id(DB::connection());
    }
}
