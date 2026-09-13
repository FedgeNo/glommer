<?php

declare(strict_types=1);

/**
 * WSToken::issue() reads WS_SECRET via Config::get('WSSecret') rather than
 * taking it as a parameter, so these tests putenv() a known secret and then
 * Config::reload() to force config.php to be re-evaluated against the new
 * environment (Config caches its values per process otherwise). This works
 * regardless of whether .env itself is readable in this environment.
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
        Config::reload();

        $token = WSToken::issue(42, 3, str_repeat('a', 64));
        $user_id = WSToken::verify($token, self::TEST_SECRET);

        $this -> assertSame(42, $user_id['userId']);
        $this -> assertSame(3, $user_id['version']);
        $this -> assertSame(str_repeat('a', 64), $user_id['sessionId']);
    }

    public function testVerifyRejectsTamperedSignature(): void
    {
        $token = '42.' . (time() + 60) . '.0000000000000000000000000000000000000000000000000000000000000000';

        $this -> assertNull(WSToken::verify($token, self::TEST_SECRET));
    }

    public function testVerifyRejectsExpiredToken(): void
    {
        putenv('WS_SECRET=' . self::TEST_SECRET);
        Config::reload();
        $token = WSToken::issue(42, 3, str_repeat('a', 64), null, time() - WSToken::TTL_SECONDS);
        $this -> assertNull(WSToken::verify($token, self::TEST_SECRET));
    }

    public function testVerifyRejectsWrongSecret(): void
    {
        putenv('WS_SECRET=' . self::TEST_SECRET);
        Config::reload();
        $token = WSToken::issue(42, 3, str_repeat('a', 64));
        $this -> assertNull(WSToken::verify($token, 'a-completely-different-secret'));
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
        Config::reload();

        $this -> assertSame('', WSToken::issue(42, 3, str_repeat('a', 64)));

        // Restore for any test that happens to run after this one.
        putenv('WS_SECRET=' . self::TEST_SECRET);
        Config::reload();
    }

    public function testLeaseCannotChangeIdentityOrExtendItsExpiry(): void
    {
        putenv('WS_SECRET=' . self::TEST_SECRET);
        Config::reload();
        [$body, $signature] = explode('.', WSToken::issue(42, 3, str_repeat('a', 64)));
        $claims = json_decode(base64_decode($body), true);

        foreach (['userId' => 99, 'version' => 4, 'sessionId' => str_repeat('b', 64), 'deviceId' => str_repeat('c', 64), 'expiresAt' => time() + 3600] as $field => $value) {
            $changed = array_replace($claims, [$field => $value]);
            $forged = base64_encode(json_encode($changed)) . '.' . $signature;
            $this -> assertNull(WSToken::verify($forged, self::TEST_SECRET), $field);
        }
    }

    public function testSignedMalformedOrOverlongLeasesAreRejected(): void
    {
        putenv('WS_SECRET=' . self::TEST_SECRET);
        Config::reload();
        $this -> assertNull(WSToken::verify(WSToken::issue(0, 0, str_repeat('a', 64)), self::TEST_SECRET));
        $this -> assertNull(WSToken::verify(WSToken::issue(1, -1, str_repeat('a', 64)), self::TEST_SECRET));
        $this -> assertNull(WSToken::verify(WSToken::issue(1, 0, 'bad'), self::TEST_SECRET));
        $this -> assertNull(WSToken::verify(WSToken::issue(1, 0, str_repeat('a', 64), null, time() + 60), self::TEST_SECRET));
        $claims = WSToken::verify(WSToken::issue(1, 0, str_repeat('a', 64)), self::TEST_SECRET);
        $claims['expiresAt'] += 600;
        $body = base64_encode(json_encode($claims));
        $this -> assertNull(WSToken::verify($body . '.' . hash_hmac('sha256', 'glommer.ws.lease.v1.' . $body, self::TEST_SECRET), self::TEST_SECRET));
    }
}
