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

    public function testALoginRehashCannotOverwriteANewerPassword(): void
    {
        $user_id = self::createUser();
        DB::run('UPDATE `Users` SET `passwordHash` = ? WHERE `userId` = ?', 'si', self::cheapHash('old password'), $user_id);
        $stale = User::load($user_id);
        DB::run('UPDATE `Users` SET `passwordHash` = ? WHERE `userId` = ?', 'si', self::cheapHash('new password'), $user_id);

        $rehash = new \ReflectionMethod(Auth::class, 'rehashIfNeeded');
        $this -> assertFalse($rehash -> invoke(null, $stale, 'old password'));
        $this -> assertTrue(User::load($user_id) -> verifyPassword('new password'));
        $this -> assertFalse(User::load($user_id) -> verifyPassword('old password'));
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

    public function testAResetInvalidatesAllOtherResetLinks(): void
    {
        $id = self::createUser();
        $first = $this -> resetToken($id);
        $second = $this -> resetToken($id);
        $this -> assertTrue(PasswordReset::consume($first, 'replacement password'));
        $this -> assertNull(PasswordReset::verify($second));
        $this -> assertFalse(PasswordReset::consume($second, 'old link password'));
    }

    public function testChangingEmailInvalidatesLinksAndRejectsStaleIssuance(): void
    {
        $id = self::createUser();
        $user = User::load($id);
        $create = new \ReflectionMethod(EmailVerification::class, 'create');
        $verification = $create -> invoke(null, $id, $user -> email);
        $reset = $this -> resetToken($id);

        $this -> assertTrue($user -> changeEmail('changed-' . bin2hex(random_bytes(6)) . '@example.test'));
        $this -> assertNull(EmailVerification::verify($verification));
        $this -> assertNull(PasswordReset::verify($reset));
        $this -> assertNull($create -> invoke(null, $id, $user -> email));
        $this -> assertNull((new \ReflectionMethod(PasswordReset::class, 'create')) -> invoke(null, $id, $user -> email));
        $this -> assertFalse((new \ReflectionMethod(EmailVerification::class, 'markVerified')) -> invoke(null, $id, $user -> email));
        $this -> assertSame(0, User::load($id) -> verified);

        $fresh = $create -> invoke(null, $id, User::load($id) -> email);
        $this -> assertSame($id, EmailVerification::verify($fresh));
        $this -> assertNull(EmailVerification::verify($fresh));
    }

    public function testRecoveryInvalidatesResetLinksFromTheReplacedAddress(): void
    {
        $id = self::createUser();
        $original = $this -> emailOf($id);
        $this -> assertTrue(User::load($id) -> changeEmail('recovery-' . bin2hex(random_bytes(6)) . '@example.test'));
        $reset = $this -> resetToken($id);
        $this -> assertTrue(EmailChangeRevert::consume($this -> revertToken($id, $original)));
        $this -> assertNull(PasswordReset::verify($reset));
    }

    public function testARevokedFirstFactorCannotCompleteTwoFactorLogin(): void
    {
        $session = $_SESSION ?? [];
        try {
            $id = self::createUser();
            Auth::beginTwoFactor(User::load($id), true, false);
            $this -> assertSame($id, Auth::pendingTwoFactorUser() -> userId);
            User::bumpSessionVersion($id);
            $this -> assertNull(Auth::pendingTwoFactorUser());
            $this -> assertFalse(isset($_SESSION['pending2FAUserId']));
            Auth::beginTwoFactor(User::load($id), false, true);
            $this -> assertSame($id, Auth::pendingTwoFactorUser() -> userId);
            Auth::clearPendingTwoFactor();
        } finally {
            $_SESSION = $session;
        }
    }

    public function testPasswordChangeAndGoogleRecoveryRollBackOnRevocationFailure(): void
    {
        $id = self::createUser();
        $user = User::load($id);
        $hash = $this -> passwordHashOf($id);
        $reset = $this -> resetToken($id);
        mysqli_query(DB::connection(), '
CREATE TRIGGER `AccountRecoveryFailRevocation` BEFORE UPDATE ON `Users` FOR EACH ROW
BEGIN
    IF NEW.`sessionVersion` <> OLD.`sessionVersion` THEN
        SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'forced revocation failure\';
    END IF;
END');
        try {
            foreach ([
                fn () => $user -> changePassword(self::cheapHash('replacement')),
                fn () => GoogleAuth::resolveUser((string) $user -> email, null),
            ] as $change) {
                try {
                    $change();
                    $this -> assertTrue(false, 'the injected failure must escape');
                } catch (\mysqli_sql_exception $exception) {
                    $this -> assertTrue(str_contains($exception -> getMessage(), 'forced revocation failure'));
                }
                $this -> assertSame($hash, $this -> passwordHashOf($id));
                $this -> assertSame($user -> sessionVersion, User::load($id) -> sessionVersion);
                $this -> assertSame(0, User::load($id) -> verified);
                $this -> assertSame($id, PasswordReset::verify($reset));
            }
        } finally {
            mysqli_query(DB::connection(), 'DROP TRIGGER `AccountRecoveryFailRevocation`');
        }

        $this -> assertNotNull($user -> changePassword(self::cheapHash('replacement')));
        $this -> assertNull(PasswordReset::verify($reset));
        $this -> assertNull($user -> changePassword(self::cheapHash('stale replacement')));
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

    public function testResetRequestsHaveAnAddressWideBudget(): void
    {
        $email = 'reset-budget-' . bin2hex(random_bytes(5)) . '@example.test';
        $rate_key = 'forgot-password-account:' . hash('sha256', strtolower($email));

        try {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $this -> assertTrue(PasswordReset::allowRequestFor($email));
            }

            $this -> assertFalse(PasswordReset::allowRequestFor(strtoupper($email)), 'case variants share the same account budget');
        } finally {
            DB::run('DELETE FROM `RateLimitAttempts` WHERE `rateKey` = ?', 's', $rate_key);
        }
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

    private function lockResult(\mysqli $connection, string $lock_name): int
    {
        $stmt = mysqli_prepare($connection, 'SELECT GET_LOCK(?, 0)');
        mysqli_stmt_bind_param($stmt, 's', $lock_name);
        mysqli_stmt_execute($stmt);

        return (int) mysqli_fetch_row(mysqli_stmt_get_result($stmt))[0];
    }

    private function releaseLock(\mysqli $connection, string $lock_name): void
    {
        $stmt = mysqli_prepare($connection, 'SELECT RELEASE_LOCK(?)');
        mysqli_stmt_bind_param($stmt, 's', $lock_name);
        mysqli_stmt_execute($stmt);
    }

    public function testTheAddressLockExcludesASecondDatabaseConnection(): void
    {
        $email = 'lock-' . bin2hex(random_bytes(4)) . '@example.test';
        $rate_key = EmailChangeRevert::addressLock($email);
        $lock_name = 'ratelimit:' . md5($rate_key);
        $second_connection = mysqli_connect(
            (string) Config::get('host'),
            (string) Config::get('username'),
            (string) Config::get('password'),
            (string) Config::get('database'),
            (int) Config::get('port')
        );

        RateLimiter::acquireLock($rate_key);

        try {
            $this -> assertSame(0, $this -> lockResult($second_connection, $lock_name), 'the second connection cannot enter the address claim');
        } finally {
            RateLimiter::releaseLock($rate_key);
        }

        try {
            $this -> assertSame(1, $this -> lockResult($second_connection, $lock_name), 'the next claimant enters after release');
        } finally {
            $this -> releaseLock($second_connection, $lock_name);
            mysqli_close($second_connection);
        }
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

    public function testALaterRevertFailureRollsTheAddressBackAndPreservesTheToken(): void
    {
        $user_id = self::createUser();
        $original = $this -> emailOf($user_id);
        $taken_over = 'late-failure-' . bin2hex(random_bytes(4)) . '@example.test';
        $token = $this -> revertToken($user_id, $original);

        DB::run('
UPDATE `Users`
    SET `email` = ?
    WHERE `userId` = ?
', 'si', $taken_over, $user_id);

        mysqli_query(DB::connection(), '
CREATE TRIGGER `AccountRecoveryTestFailSessionBump`
    BEFORE UPDATE ON `Users`
    FOR EACH ROW
    BEGIN
        IF NEW.`sessionVersion` <> OLD.`sessionVersion` THEN
            SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT = \'forced late recovery failure\';
        END IF;
    END
');

        try {
            try {
                EmailChangeRevert::consume($token);
                $this -> assertTrue(false, 'the forced late failure must escape');
            } catch (\mysqli_sql_exception $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'forced late recovery failure'));
            }

            $this -> assertSame($taken_over, $this -> emailOf($user_id), 'the earlier address update rolled back');
        } finally {
            mysqli_query(DB::connection(), 'DROP TRIGGER IF EXISTS `AccountRecoveryTestFailSessionBump`');
        }

        $this -> assertTrue(EmailChangeRevert::consume($token), 'rollback left the recovery token usable');
        $this -> assertSame($original, $this -> emailOf($user_id));
    }
}
