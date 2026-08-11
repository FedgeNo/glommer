<?php

declare(strict_types=1);

/**
 * A username is permanent here, so deleting the account must not release it.
 * Federation is what makes that more than tidiness: an actor URI is built from
 * the username, so a recycled name inherits the dead account's URI and every
 * remote server still holding a follow starts delivering a stranger's posts
 * into it.
 */
class RetiredUsernameTest extends DatabaseTestCase
{
    public function testDeletingAnAccountRetiresItsName(): void
    {
        $user_id = self::createUser();

        $user = DB::row('
SELECT `slug`
    FROM `Users`
    WHERE `userId` = ?
', 'User', 'i', $user_id);

        $slug = (string) $user -> slug;

        $this -> assertFalse(RetiredUsername::isRetired($slug));

        User::delete($user_id);

        $this -> assertTrue(RetiredUsername::isRetired($slug), 'the name should not be released with the account');
    }

    public function testARetiredNameStaysRetiredIfRetiredAgain(): void
    {
        $slug = 'test-retired-' . bin2hex(random_bytes(6));

        RetiredUsername::retire($slug);
        RetiredUsername::retire($slug);

        $this -> assertTrue(RetiredUsername::isRetired($slug));
    }

    public function testANameNobodyEverHadIsNotRetired(): void
    {
        $this -> assertFalse(RetiredUsername::isRetired('test-never-used-' . bin2hex(random_bytes(6))));
    }

    public function testAShadowAccountsHandleIsNotReserved(): void
    {
        // A remote handle is not this server's to hold. Reserving it would also
        // block re-federating with that same account later.
        $handle = 'someone-' . bin2hex(random_bytes(4)) . '@remote.invalid';

        DB::run('
INSERT INTO `Users` (`slug`, `email`, `passwordHash`, `remoteActorURI`)
    VALUES (?, ?, ?, ?)
', 'ssss', $handle, 'test-' . bin2hex(random_bytes(6)) . '@example.test', self::cheapHash('x'), 'https://remote.invalid/users/' . bin2hex(random_bytes(4)));

        $shadow_id = (int) mysqli_insert_id(DB::connection());

        User::delete($shadow_id);

        $this -> assertFalse(RetiredUsername::isRetired($handle));
    }

    public function testAnEmptyNameIsNeverRecorded(): void
    {
        RetiredUsername::retire('');

        $this -> assertFalse(RetiredUsername::isRetired(''));
    }
}
