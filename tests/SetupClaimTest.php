<?php

declare(strict_types=1);

class SetupClaimTest extends DatabaseTestCase
{
    private function withClaim(callable $work): void
    {
        $path = sys_get_temp_dir() . '/glommer-claim-test-' . bin2hex(random_bytes(8));
        $property = new \ReflectionProperty(SetupClaim::class, 'path');
        $original = $property -> getValue();
        $session = $_SESSION ?? [];
        $property -> setValue(null, $path);
        $_SESSION = [];
        DB::run('UPDATE `SetupState` SET `completed` = 0 WHERE `setupId` = 1');
        try {
            $work($path);
        } finally {
            DB::run('UPDATE `SetupState` SET `completed` = 1 WHERE `setupId` = 1');
            $_SESSION = $session;
            $property -> setValue(null, $original);
            if (is_file($path)) unlink($path);
        }
    }

    public function testAbsentWrongAndExpiredCodesCannotAuthorizeABrowser(): void
    {
        $this -> withClaim(function (string $path): void {
            $this -> assertFalse(SetupClaim::authorized());
            $code = SetupClaim::issue();
            $this -> assertSame(0600, fileperms($path) & 0777);
            $this -> assertFalse(str_contains(file_get_contents($path), $code));
            $this -> assertFalse(SetupClaim::accept(str_repeat('0', 64)));
            $state = json_decode(file_get_contents($path), true);
            $state['expiresAt'] = time() - 1;
            file_put_contents($path, json_encode($state));
            $this -> assertFalse(SetupClaim::accept($code));
            $this -> assertFalse(SetupClaim::authorized());
        });
    }

    public function testCodeIsSingleUseAndGrantBelongsOnlyToTheClaimingSession(): void
    {
        $this -> withClaim(function (): void {
            $code = SetupClaim::issue();
            $this -> assertTrue(SetupClaim::accept($code));
            $claiming_session = $_SESSION;
            $this -> assertTrue(SetupClaim::authorized());
            $this -> assertFalse(SetupClaim::accept($code));
            $_SESSION = [];
            $this -> assertFalse(SetupClaim::authorized());
            $called = false;
            try {
                SetupClaim::register(static function () use (&$called): void { $called = true; });
                $this -> assertTrue(false);
            } catch (\RuntimeException $exception) {
                $this -> assertFalse($called);
            }
            $_SESSION = $claiming_session;
            $this -> assertSame('administrator', SetupClaim::register(static fn (bool $admin): string => $admin ? 'administrator' : 'member'));
            $this -> assertFalse(SetupClaim::required());
            $this -> assertFalse(SetupClaim::authorized());
            $this -> assertSame('member', SetupClaim::register(static fn (bool $admin): string => $admin ? 'administrator' : 'member'));
        });
    }

    public function testRegistrationRollbackPreservesClaimForRetry(): void
    {
        $this -> withClaim(function (): void {
            SetupClaim::accept(SetupClaim::issue());
            $id = self::createUser();
            try {
                try {
                    SetupClaim::register(static function () use ($id): void {
                        DB::run('UPDATE `Users` SET `title` = ? WHERE `userId` = ?', 'si', 'must roll back', $id);
                        throw new \RuntimeException('injected registration failure');
                    });
                    $this -> assertTrue(false);
                } catch (\RuntimeException $exception) {
                    $this -> assertSame('injected registration failure', $exception -> getMessage());
                }
                $this -> assertNull(User::load($id) -> title);
                $this -> assertTrue(SetupClaim::required());
                $this -> assertTrue(SetupClaim::authorized());
            } finally {
                DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $id);
            }
        });
    }

    public function testReplacementCodeInvalidatesEarlierCodeAndSessionGrant(): void
    {
        $this -> withClaim(function (): void {
            $old = SetupClaim::issue();
            SetupClaim::accept($old);
            $new = SetupClaim::issue();
            $this -> assertFalse(SetupClaim::authorized());
            $this -> assertFalse(SetupClaim::accept($old));
            $this -> assertTrue(SetupClaim::accept($new));
        });
    }

    public function testGoogleAndRemoteCreationCannotBypassAnUnclaimedInstallation(): void
    {
        $this -> withClaim(function (): void {
            $this -> assertNull(GoogleAuth::resolveUser('new-' . bin2hex(random_bytes(8)) . '@example.test', 'Unclaimed'));
            try {
                RemoteActor::upsert(['id' => 'https://unclaimed.invalid/actor']);
                $this -> assertTrue(false);
            } catch (\RuntimeException $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'operator claims'));
            }
            $this -> assertTrue(SetupClaim::required());
        });
    }
}
