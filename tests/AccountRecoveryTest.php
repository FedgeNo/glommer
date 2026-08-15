<?php

declare(strict_types=1);

/**
 * The two ways back into an account somebody else has taken: the password
 * reset link, and the revert link sent to the address that was replaced.
 *
 * Both are used by exactly one person in exactly one bad situation, so both
 * have to be all-or-nothing. A reset that can be spent twice hands the account
 * to whoever races the person it was sent to; a revert that restores the
 * address and then fails to end the sessions leaves the account reading as its
 * owner's while the person who took it is still signed in.
 */
class AccountRecoveryTest extends DatabaseTestCase
{
    /** A reset token for a user, made the way sendFor() makes one. */
    private function resetToken(int $user_id): string
    {
        $create = new \ReflectionMethod(PasswordReset::class, 'create');
        $create -> setAccessible(true);

        return (string) $create -> invoke(null, $user_id);
    }

    private function passwordHashOf(int $user_id): string
    {
        return (string) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `passwordHash`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['passwordHash'];
    }

    public function testAResetTokenWorksOnce(): void
    {
        $user_id = self::createUser();
        $token = $this -> resetToken($user_id);

        $this -> assertTrue(PasswordReset::consume($token, 'the first password'));
        $this -> assertTrue(password_verify('the first password', $this -> passwordHashOf($user_id)));
    }

    /**
     * The race the claim exists for: two requests holding one token. Whichever
     * is second must be refused, or the password that stands is the one set by
     * whoever else had the link.
     */
    public function testTheSecondUseOfAResetTokenIsRefused(): void
    {
        $user_id = self::createUser();
        $token = $this -> resetToken($user_id);

        $this -> assertTrue(PasswordReset::consume($token, 'the owner\'s password'));
        $this -> assertFalse(PasswordReset::consume($token, 'somebody else\'s password'), 'the token was already spent');

        $this -> assertTrue(
            password_verify('the owner\'s password', $this -> passwordHashOf($user_id)),
            'the password that stands is the one set by the first use'
        );
    }

    public function testAResetTokenThatWasNeverIssuedIsRefused(): void
    {
        $this -> assertFalse(PasswordReset::consume(bin2hex(random_bytes(32)), 'nice try'));
    }

    /** Spending the token also ends whatever the old password was signed into. */
    public function testAResetEndsTheSessionsTheOldPasswordOpened(): void
    {
        $user_id = self::createUser();
        $before = (int) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['sessionVersion'];

        PasswordReset::consume($this -> resetToken($user_id), 'a new password');

        $after = (int) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['sessionVersion'];

        $this -> assertTrue($after > $before, 'the session version moved');
    }

    // ---- The revert link ----

    private function emailOf(int $user_id): string
    {
        return (string) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `email`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['email'];
    }

    private function revertToken(int $user_id, string $previous_email): string
    {
        $create = new \ReflectionMethod(EmailChangeRevert::class, 'create');
        $create -> setAccessible(true);

        return (string) $create -> invoke(null, $user_id, $previous_email);
    }

    public function testARevertPutsTheAddressBackAndEndsTheSessions(): void
    {
        $user_id = self::createUser();
        $original = $this -> emailOf($user_id);
        $taken_over = 'attacker-' . bin2hex(random_bytes(4)) . '@example.test';

        DB::run('
UPDATE `Users`
    SET `email` = ?
    WHERE `userId` = ?
', 'si', $taken_over, $user_id);

        $before = (int) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['sessionVersion'];

        $this -> assertTrue(EmailChangeRevert::consume($this -> revertToken($user_id, $original)));
        $this -> assertSame($original, $this -> emailOf($user_id));

        $after = (int) mysqli_fetch_assoc(mysqli_stmt_get_result(DB::run('
SELECT `sessionVersion`
    FROM `Users`
    WHERE `userId` = ?
', 'i', $user_id)))['sessionVersion'];

        $this -> assertTrue($after > $before, 'the sessions the change opened are gone');
    }

    public function testARevertTokenWorksOnce(): void
    {
        $user_id = self::createUser();
        $original = $this -> emailOf($user_id);
        $token = $this -> revertToken($user_id, $original);

        $this -> assertTrue(EmailChangeRevert::consume($token));
        $this -> assertFalse(EmailChangeRevert::consume($token), 'the reservation went with it');
    }

    /**
     * The address is not put back where somebody else has taken it in the
     * meantime - and the account is left as it was rather than half-reverted.
     */
    public function testARevertToAnAddressSomebodyElseNowHoldsChangesNothing(): void
    {
        $user_id = self::createUser();
        $other_id = self::createUser();

        $original = $this -> emailOf($user_id);
        $token = $this -> revertToken($user_id, $original);

        DB::run('
UPDATE `Users`
    SET `email` = ?
    WHERE `userId` = ?
', 'si', 'moved-' . bin2hex(random_bytes(4)) . '@example.test', $user_id);

        // Somebody else takes the address in between.
        DB::run('
UPDATE `Users`
    SET `email` = ?
    WHERE `userId` = ?
', 'si', $original, $other_id);

        $before = $this -> emailOf($user_id);

        $this -> assertFalse(EmailChangeRevert::consume($token));
        $this -> assertSame($before, $this -> emailOf($user_id), 'nothing moved');
        $this -> assertSame($original, $this -> emailOf($other_id), 'and the other account kept it');
    }
}
