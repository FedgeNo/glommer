<?php

declare(strict_types=1);

class WSRevocationTest extends TestCase
{
    private const SECRET = 'isolated-ws-revocation-test-secret';

    private function fixture(callable $work): void
    {
        Env::get('WS_SECRET');
        $original = getenv('WS_SECRET');
        putenv('WS_SECRET=' . self::SECRET);
        Config::reload();

        try {
            $work(new WSRevocation());
        } finally {
            putenv($original === false ? 'WS_SECRET' : 'WS_SECRET=' . $original);
            Config::reload();
        }
    }

    private function claims(int $user = 7, int $version = 2, string $session = 'a', ?string $device = null): array
    {
        return WSToken::verify(WSToken::issue($user, $version, str_repeat($session, 64), $device === null ? null : str_repeat($device, 64)), self::SECRET);
    }

    public function testVersionRevocationPreservesOtherUsersAndNewerLogins(): void
    {
        $this -> fixture(function (WSRevocation $rules): void {
            $this -> assertSame(7, $rules -> accept(WSRevocation::issue(7, 'version', 3), self::SECRET));
            $this -> assertFalse($rules -> allows($this -> claims()));
            $this -> assertTrue($rules -> allows($this -> claims(8)));
            $this -> assertTrue($rules -> allows($this -> claims(7, 3)));
            $this -> assertTrue($rules -> allows($this -> claims(7, 4)));
        });
    }

    public function testSessionAndDeviceCommandsCannotBroadenTheirScope(): void
    {
        $this -> fixture(function (WSRevocation $rules): void {
            $rules -> accept(WSRevocation::issue(7, 'session', str_repeat('a', 64)), self::SECRET);
            $this -> assertFalse($rules -> allows($this -> claims()));
            $this -> assertTrue($rules -> allows($this -> claims(7, 2, 'b')));
            $this -> assertTrue($rules -> allows($this -> claims(8)));
            $rules -> accept(WSRevocation::issue(7, 'device', str_repeat('c', 64)), self::SECRET);
            $this -> assertFalse($rules -> allows($this -> claims(7, 2, 'b', 'c')));
            $this -> assertTrue($rules -> allows($this -> claims(7, 2, 'b', 'd')));
        });
    }

    public function testTamperingWrongKeysMissingKeysAndReplayHaveNoAuthority(): void
    {
        $this -> fixture(function (WSRevocation $rules): void {
            $line = WSRevocation::issue(7, 'version', 3);
            $this -> assertNull($rules -> accept($line, 'wrong-secret'));
            $this -> assertNull($rules -> accept($line, ''));
            $this -> assertNull($rules -> accept($line, null));
            $request = json_decode($line, true);
            $body = json_decode(base64_decode($request['control']), true);

            foreach (['userId' => 8, 'scope' => 'session', 'value' => 999, 'issuedAt' => time() + 1, 'nonce' => str_repeat('f', 32)] as $field => $value) {
                $changed = $request;
                $changed['control'] = base64_encode(json_encode(array_replace($body, [$field => $value])));
                $this -> assertNull($rules -> accept(json_encode($changed), self::SECRET), $field);
            }

            $this -> assertTrue($rules -> allows($this -> claims()));
            $this -> assertSame(7, $rules -> accept($line, self::SECRET));
            $this -> assertNull($rules -> accept($line, self::SECRET));
            $this -> assertTrue($rules -> allows($this -> claims(8)));
        });
    }

    public function testBrowserTokensAndOpaquePushPayloadsCannotBecomeControlCommands(): void
    {
        $this -> fixture(function (WSRevocation $rules): void {
            $token = WSToken::issue(7, 2, str_repeat('a', 64));
            [$body, $signature] = explode('.', $token);
            $this -> assertNull($rules -> accept(json_encode(['control' => $body, 'signature' => $signature]), self::SECRET));
            $line = WSRevocation::issue(7, 'version', 3);
            $this -> assertNull(WSToken::verify($line, self::SECRET));
            $different_purpose = json_decode($line, true);
            $different_purpose['signature'] = hash_hmac('sha256', 'glommer.ws.push.v1.' . $different_purpose['control'], self::SECRET);
            $this -> assertNull($rules -> accept(json_encode($different_purpose), self::SECRET));
            $this -> assertNull($rules -> accept(json_encode(['secret' => self::SECRET, 'userId' => 7, 'payload' => json_decode($line, true)]), self::SECRET));
            $this -> assertTrue($rules -> allows($this -> claims()));
        });
    }

    public function testEvenSignedExpiredFutureOrMalformedCommandsAreRejected(): void
    {
        $this -> fixture(function (WSRevocation $rules): void {
            $valid = json_decode(WSRevocation::issue(7, 'version', 3), true);
            $command = json_decode(base64_decode($valid['control']), true);

            foreach (['issuedAt' => [time() - 30, time() + 30], 'userId' => [0, -1, '7', []], 'scope' => ['all', []], 'value' => [0, '3', []], 'nonce' => ['', []]] as $field => $values) {
                foreach ($values as $value) {
                    $body = base64_encode(json_encode(array_replace($command, [$field => $value])));
                    $line = json_encode(['control' => $body, 'signature' => hash_hmac('sha256', 'glommer.ws.revoke.v1.' . $body, self::SECRET)]);
                    $this -> assertNull($rules -> accept($line, self::SECRET), $field);
                }
            }

            $this -> assertTrue($rules -> allows($this -> claims()));
        });
    }
}
