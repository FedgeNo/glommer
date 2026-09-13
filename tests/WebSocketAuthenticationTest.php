<?php

declare(strict_types=1);

require_once __DIR__ . '/WebSocketFixture.php';

class WebSocketAuthenticationTest extends DatabaseTestCase
{
    private function fixture(callable $work): void
    {
        $session = $_SESSION ?? [];
        $daemon = new WebSocketFixture();
        $user_id = self::createUser();
        $other_id = self::createUser();
        $_SESSION = ['userId' => $user_id, 'sessionVersion' => 0];
        Auth::clearUserCache();

        try {
            $work($daemon, $user_id, $other_id);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` IN (?, ?)', 'ii', $user_id, $other_id);
            $_SESSION = $session;
            Auth::clearUserCache();
            $daemon -> close();
        }
    }

    public function testCredentialRevocationIsDeliveredAfterCommitAndNeverAfterRollback(): void
    {
        $this -> fixture(function (WebSocketFixture $daemon, int $user_id, int $other_id): void {
            $token = WebSocketAuthentication::token();
            $daemon -> connect($token);
            $daemon -> connect(WSToken::issue($other_id, 0, str_repeat('b', 64)));
            $daemon -> waitForClients($user_id, 1);
            $daemon -> waitForClients($other_id, 1);

            try {
                DB::transaction(function () use ($daemon, $user_id): void {
                    User::bumpSessionVersion($user_id);
                    $this -> assertSame(1, $daemon -> push($user_id), 'no command before commit');
                    throw new \RuntimeException('fixture rollback');
                });
            } catch (\RuntimeException $exception) {
                $this -> assertSame('fixture rollback', $exception -> getMessage());
            }

            $this -> assertSame(0, User::load($user_id) -> sessionVersion);
            $this -> assertSame(1, $daemon -> push($user_id));
            $this -> assertTrue(WebSocketAuthentication::token() !== '');
            DB::transaction(static fn (): int => User::bumpSessionVersion($user_id));
            $this -> assertSame(0, $daemon -> push($user_id));
            $this -> assertSame(1, $daemon -> push($other_id));
            $this -> assertSame('', WebSocketAuthentication::token(), 'stale PHP session cannot renew');
            $_SESSION['sessionVersion'] = 1;
            $daemon -> connect(WebSocketAuthentication::token());
            $daemon -> waitForClients($user_id, 1);
        });
    }

    public function testLogoutControlOnlyTargetsTheServerOwnedSessionIdentity(): void
    {
        $this -> fixture(function (WebSocketFixture $daemon, int $user_id, int $other_id): void {
            $token = WebSocketAuthentication::token();
            $daemon -> connect($token);
            $daemon -> connect(WSToken::issue($user_id, 0, str_repeat('b', 64)));
            $daemon -> connect(WSToken::issue($other_id, 0, str_repeat('c', 64)));
            $daemon -> waitForClients($user_id, 2);
            $daemon -> waitForClients($other_id, 1);
            WebSocketAuthentication::revokeCurrent();
            $this -> assertSame(1, $daemon -> push($user_id));
            $this -> assertSame(1, $daemon -> push($other_id));
            $daemon -> connect($token);
            usleep(20000);
            $this -> assertSame(1, $daemon -> push($user_id));
        });
    }

    public function testDeviceRevocationCannotBeUsedAgainstAnotherAccountsToken(): void
    {
        $this -> fixture(function (WebSocketFixture $daemon, int $user_id, int $other_id): void {
            $create = new \ReflectionMethod(RememberToken::class, 'create');
            [$selector] = explode(':', $create -> invoke(null, $user_id), 2);
            $device = DB::row('SELECT `tokenId` FROM `RememberTokens` WHERE `selector` = ?', 'RememberTokenData', 's', $selector);
            $_SESSION['wsRememberSelector'] = $selector;
            $daemon -> connect(WebSocketAuthentication::token());
            $daemon -> waitForClients($user_id, 1);
            $this -> assertFalse(RememberToken::revoke($device -> tokenId, $other_id));
            $this -> assertSame(1, $daemon -> push($user_id));
            $this -> assertTrue(RememberToken::revoke($device -> tokenId, $user_id));
            $this -> assertSame(0, $daemon -> push($user_id));
            $this -> assertSame('', WebSocketAuthentication::token());
        });
    }

    public function testBannedDeletedAndMissingAccountsCannotObtainRenewals(): void
    {
        $this -> fixture(function (WebSocketFixture $daemon, int $user_id): void {
            Auth::user(); // Populate the ordinary request cache before the change.
            DB::run('UPDATE `Users` SET `banned` = 1 WHERE `userId` = ?', 'i', $user_id);
            $this -> assertSame('', WebSocketAuthentication::token(), 'must not trust the earlier cached user');
            DB::run('UPDATE `Users` SET `banned` = 0 WHERE `userId` = ?', 'i', $user_id);
            $daemon -> connect(WebSocketAuthentication::token());
            $daemon -> waitForClients($user_id, 1);
            User::delete($user_id);
            $this -> assertSame(0, $daemon -> push($user_id));
            $this -> assertSame('', WebSocketAuthentication::token());
            $_SESSION = [];
            $this -> assertSame('', WebSocketAuthentication::token());
        });
    }

    public function testAnExistingSessionOnlyBindsADeviceAfterVerifyingItsCookie(): void
    {
        $this -> fixture(function (WebSocketFixture $daemon, int $user_id, int $other_id): void {
            $cookies = $_COOKIE;
            $create = new \ReflectionMethod(RememberToken::class, 'create');
            $cookie = $create -> invoke(null, $user_id);
            [$selector] = explode(':', $cookie, 2);
            try {
                $_COOKIE[Cookie::name('rememberToken')] = $selector . ':' . str_repeat('0', 64);
                $this -> assertNull(RememberToken::authenticatedSelector($user_id));
                $_COOKIE[Cookie::name('rememberToken')] = $cookie;
                $this -> assertNull(RememberToken::authenticatedSelector($other_id));
                $this -> assertSame($selector, RememberToken::authenticatedSelector($user_id));
                $lease = WSToken::verify(WebSocketAuthentication::token(), WebSocketFixture::SECRET);
                $this -> assertSame(WebSocketAuthentication::deviceId($selector), $lease['deviceId']);
            } finally {
                $_COOKIE = $cookies;
            }
        });
    }
}
