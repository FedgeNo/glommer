<?php

declare(strict_types=1);

/**
 * WSToken::issue() reads WS_SECRET via config.php/Env rather than taking it
 * as a parameter, so these tests putenv() a known secret first - Env::get()
 * calls getenv() directly on every call (not just once, cached), so this
 * works regardless of whether .env itself is readable in this environment.
 * putenv() is process-global, so it deliberately leaves WS_SECRET set for
 * the rest of this test run - harmless today since no other suite reads it,
 * but worth knowing if that changes.
 */
class WSTokenTest extends TestCase
{
    private const TEST_SECRET = 'test-secret-do-not-use-in-real-env';

    public function testIssueThenVerifyRoundTripsTheUserId(): void
    {
        putenv('WS_SECRET=' . self::TEST_SECRET);

        $token = WSToken::issue(42);
        $user_id = WSToken::verify($token, self::TEST_SECRET);

        $this -> assertSame(42, $user_id);
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $token = '42.' . (time() + 60) . '.0000000000000000000000000000000000000000000000000000000000000000';

        $this -> assertNull(WSToken::verify($token, self::TEST_SECRET));
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        $expired_at = time() - 10;
        $payload = '42.' . $expired_at;
        $signature = hash_hmac('sha256', $payload, self::TEST_SECRET);

        $this -> assertNull(WSToken::verify($payload . '.' . $signature, self::TEST_SECRET));
    }

    public function testVerifyRejectsWrongSecret(): void
    {
        $expires_at = time() + 60;
        $payload = '42.' . $expires_at;
        $signature = hash_hmac('sha256', $payload, self::TEST_SECRET);

        $this -> assertNull(WSToken::verify($payload . '.' . $signature, 'a-completely-different-secret'));
    }

    public function testVerifyRejectsMalformedToken(): void
    {
        $this -> assertNull(WSToken::verify('not-even-close-to-valid', self::TEST_SECRET));
        $this -> assertNull(WSToken::verify('42.abc.signature', self::TEST_SECRET));
        $this -> assertNull(WSToken::verify('42.123', self::TEST_SECRET));
    }

    public function testVerifyRejectsWhenSecretIsEmpty(): void
    {
        $expires_at = time() + 60;
        $payload = '42.' . $expires_at;
        $signature = hash_hmac('sha256', $payload, self::TEST_SECRET);

        $this -> assertNull(WSToken::verify($payload . '.' . $signature, ''));
        $this -> assertNull(WSToken::verify($payload . '.' . $signature, null));
    }

    public function testIssueReturnsEmptyStringWhenSecretUnset(): void
    {
        putenv('WS_SECRET'); // unset

        $this -> assertSame('', WSToken::issue(42));

        // Restore for any test that happens to run after this one.
        putenv('WS_SECRET=' . self::TEST_SECRET);
    }
}
