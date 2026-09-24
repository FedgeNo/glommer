<?php

declare(strict_types=1);

class AuthCredentialsTest extends DatabaseTestCase
{
    private function cpuSeconds(): float
    {
        $usage = getrusage();
        return $usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1000000;
    }

    public function testMissingAccountsStillPerformPasswordWork(): void
    {
        // Calibrate CPU work rather than wall time so DB/network waits cannot
        // conceal an early return. This hash is a benchmark, not a fixture user.
        $reference = password_hash('credential timing reference', PASSWORD_DEFAULT);
        $start = $this -> cpuSeconds();
        password_verify('wrong password', $reference);
        $minimum = ($this -> cpuSeconds() - $start) / 5;
        $missing = 'missing-' . bin2hex(random_bytes(12));
        foreach ([$missing, $missing . '@example.test'] as $identifier) {
            $start = $this -> cpuSeconds();
            $result = Auth::verifyCredentials($identifier, 'dummy credential check');
            $spent = $this -> cpuSeconds() - $start;
            $this -> assertNull($result);
            $this -> assertTrue($spent >= $minimum, 'A rejected identifier must still perform password verification work.');
        }
    }

    public function testKnownCredentialsAndBannedAccountSemanticsRemainUnchanged(): void
    {
        $user_id = self::createUser();
        try {
            $hash = self::cheapHash('the correct password');
            DB::run('UPDATE `Users` SET `passwordHash` = ? WHERE `userId` = ?', 'si', $hash, $user_id);
            $user = User::load($user_id);
            $this -> assertNull(Auth::verifyCredentials($user -> slug, 'incorrect'));
            $this -> assertNull(Auth::verifyCredentials($user -> email, 'incorrect'));
            $this -> assertSame($user_id, (int) Auth::verifyCredentials($user -> slug, 'the correct password') -> userId);
            $this -> assertSame($user_id, (int) Auth::verifyCredentials($user -> email, 'the correct password') -> userId);
            DB::run('UPDATE `Users` SET `banned` = 1 WHERE `userId` = ?', 'i', $user_id);
            $verified = Auth::verifyCredentials($user -> slug, 'the correct password');
            $this -> assertSame($user_id, (int) $verified -> userId);
            $this -> assertTrue((bool) $verified -> banned);
            $this -> assertNull(Auth::attempt($user -> slug, 'the correct password'));
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $user_id);
        }
    }
}
