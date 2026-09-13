<?php

declare(strict_types=1);

/** Exercises real issuance/verification while replacing only the SMTP transport. */
class TwoFactorMailFixture extends TwoFactor
{
    public static array $codes = [];
    public static bool $succeed = true;
    public static mixed $duringSend = null;

    protected static function deliverCode(User $user, string $code): bool
    {
        self::$codes[] = $code;
        if (self::$duringSend !== null) {
            (self::$duringSend)($user);
        }
        return self::$succeed;
    }
}

class TwoFactorTest extends DatabaseTestCase
{
    private function withUser(callable $test): void
    {
        $old_secret = getenv('WS_SECRET');
        putenv('WS_SECRET=' . bin2hex(random_bytes(32)));
        Config::reload();
        $id = self::createUser();
        DB::run('UPDATE `Users` SET `twoFactorEnabled` = 1 WHERE `userId` = ?', 'i', $id);
        TwoFactorMailFixture::$codes = [];
        TwoFactorMailFixture::$succeed = true;
        TwoFactorMailFixture::$duringSend = null;
        try {
            $test(User::load($id));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $id);
            TwoFactorMailFixture::$duringSend = null;
            putenv($old_secret === false ? 'WS_SECRET' : 'WS_SECRET=' . $old_secret);
            Config::reload();
        }
    }

    private static function code(User $user): ?object
    {
        return DB::row('SELECT * FROM `TwoFactorCodes` WHERE `userId` = ?', 'stdClass', 'i', $user -> userId);
    }

    private static function endCooldown(User $user): void
    {
        DB::run('UPDATE `TwoFactorEmails` SET `createdAt` = NOW() - INTERVAL 61 SECOND WHERE `userId` = ?', 'i', $user -> userId);
    }

    public function testRepeatedLoginReusesCodeWithoutExtendingExpiryOrResettingAttempts(): void
    {
        $this -> withUser(function (User $user): void {
            $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            $code = TwoFactorMailFixture::$codes[0];
            $before = self::code($user);
            $this -> assertFalse(TwoFactor::verifyCode($user -> userId, 'wrong'));
            $this -> assertSame('ready', TwoFactorMailFixture::sendCode($user));
            $this -> assertCount(1, TwoFactorMailFixture::$codes);
            self::endCooldown($user);
            $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            $this -> assertSame($code, TwoFactorMailFixture::$codes[1]);
            $after = self::code($user);
            $this -> assertSame($before -> expiresAt, $after -> expiresAt);
            $this -> assertSame(1, (int) $after -> attempts);
            $this -> assertSame(hash('sha256', $code), $after -> codeHash);
            $this -> assertTrue(TwoFactor::verifyCode($user -> userId, $code));
            $this -> assertFalse(TwoFactor::verifyCode($user -> userId, $code));
        });
    }

    public function testThreeEmailsPerRollingWindowAndConsumptionDoesNotResetTheLimit(): void
    {
        $this -> withUser(function (User $user): void {
            for ($i = 0; $i < 3; $i++) {
                self::endCooldown($user);
                $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            }
            self::endCooldown($user);
            $this -> assertSame('ready', TwoFactorMailFixture::sendCode($user));
            $this -> assertCount(3, TwoFactorMailFixture::$codes);
            $this -> assertTrue(TwoFactor::verifyCode($user -> userId, TwoFactorMailFixture::$codes[0]));
            $this -> assertSame('limited', TwoFactorMailFixture::sendCode($user));
            $this -> assertNull(self::code($user));
            DB::run('UPDATE `TwoFactorEmails` SET `createdAt` = NOW() - INTERVAL 16 MINUTE WHERE `userId` = ?', 'i', $user -> userId);
            $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            $this -> assertCount(4, TwoFactorMailFixture::$codes);
        });
    }

    public function testInFlightMailHasAlreadyReservedItsAllowance(): void
    {
        $this -> withUser(function (User $user): void {
            TwoFactorMailFixture::$duringSend = function (User $user): void {
                $this -> assertSame('limited', TwoFactorMailFixture::sendCode($user));
            };
            $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            $this -> assertCount(1, TwoFactorMailFixture::$codes);
        });
    }

    public function testFailedMailIsLimitedAndRetriesTheSameCode(): void
    {
        $this -> withUser(function (User $user): void {
            TwoFactorMailFixture::$succeed = false;
            $this -> assertSame('failed', TwoFactorMailFixture::sendCode($user));
            $this -> assertSame('limited', TwoFactorMailFixture::sendCode($user));
            self::endCooldown($user);
            TwoFactorMailFixture::$succeed = true;
            $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            $this -> assertSame(TwoFactorMailFixture::$codes[0], TwoFactorMailFixture::$codes[1]);
        });
    }

    public function testBurnedCodeCannotBeRefreshedByRestartingLogin(): void
    {
        $this -> withUser(function (User $user): void {
            TwoFactorMailFixture::sendCode($user);
            for ($i = 0; $i < 5; $i++) {
                $this -> assertFalse(TwoFactor::verifyCode($user -> userId, 'wrong'));
            }
            self::endCooldown($user);
            $this -> assertSame('limited', TwoFactorMailFixture::sendCode($user));
            $this -> assertFalse(TwoFactor::verifyCode($user -> userId, TwoFactorMailFixture::$codes[0]));
            $this -> assertSame(5, (int) self::code($user) -> attempts);
            DB::run('UPDATE `TwoFactorCodes` SET `expiresAt` = NOW() - INTERVAL 1 SECOND WHERE `userId` = ?', 'i', $user -> userId);
            $this -> assertSame('sent', TwoFactorMailFixture::sendCode($user));
            $this -> assertSame(0, (int) self::code($user) -> attempts);
        });
    }

    public function testLegacyOrSecretRotatedCodeIsNotReplacedBeforeExpiry(): void
    {
        $this -> withUser(function (User $user): void {
            TwoFactorMailFixture::sendCode($user);
            $code = TwoFactorMailFixture::$codes[0];
            self::endCooldown($user);
            putenv('WS_SECRET=' . bin2hex(random_bytes(32)));
            Config::reload();
            $this -> assertSame('ready', TwoFactorMailFixture::sendCode($user));
            $this -> assertCount(1, TwoFactorMailFixture::$codes);
            $this -> assertTrue(TwoFactor::verifyCode($user -> userId, $code));
            DB::run('INSERT INTO `TwoFactorCodes` (`userId`, `codeHash`, `expiresAt`) VALUES (?, ?, NOW() + INTERVAL 5 MINUTE)', 'is', $user -> userId, hash('sha256', '123456'));
            TwoFactorMailFixture::sendCode($user);
            $this -> assertTrue(TwoFactor::verifyCode($user -> userId, '123456'));
        });
    }

    public function testRevokedFirstFactorCannotIssueMail(): void
    {
        $this -> withUser(function (User $user): void {
            User::bumpSessionVersion($user -> userId);
            $this -> assertSame('failed', TwoFactorMailFixture::sendCode($user));
            $this -> assertCount(0, TwoFactorMailFixture::$codes);
        });
    }
}
