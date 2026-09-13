<?php

declare(strict_types=1);

class DeletedActorTest extends DatabaseTestCase
{
    private function withAccount(callable $test): void
    {
        $id = self::createUser();
        $user = User::load($id);
        $old_key = getenv('ACTIVITYPUB_ENCRYPTION_KEY');
        $key = bin2hex(random_bytes(32));
        putenv('ACTIVITYPUB_ENCRYPTION_KEY=' . $key);
        $pair = ActivityPubKeys::generateKeypair();
        DB::run('UPDATE `Users` SET `actorPublicKeyPem` = ?, `actorEncryptedPrivateKey` = ? WHERE `userId` = ?',
            'ssi', $pair['publicKeyPem'], ActivityPubKeys::encryptPrivateKey($pair['privateKeyPem'], $key), $id);
        foreach ([1, 2] as $index) {
            FediverseFollower::add($id, 'https://delete-' . $index . '.invalid/actor', 'https://delete-' . $index . '.invalid/inbox', null, 'https://delete-' . $index . '.invalid/follow');
        }
        try {
            $test(User::load($id), $pair);
        } finally {
            DB::run('DELETE FROM `Users` WHERE `userId` = ?', 'i', $id);
            DB::run('DELETE FROM `DeletedActors` WHERE `slug` = ?', 's', $user -> slug);
            DB::run('DELETE FROM `RetiredUsernames` WHERE `slug` = ?', 's', $user -> slug);
            putenv($old_key === false ? 'ACTIVITYPUB_ENCRYPTION_KEY' : 'ACTIVITYPUB_ENCRYPTION_KEY=' . $old_key);
        }
    }

    private static function deliveries(User $user): array
    {
        return DB::rows('SELECT `d`.* FROM `FediverseDeliveries` `d` JOIN `DeletedActors` `a` USING (`deletedActorId`) WHERE `a`.`slug` = ?', 'FediverseDeliveryData', 's', $user -> slug);
    }

    public function testPasswordAndGoogleOnlyAccountsKeepSignableDeletesAfterLocalRemoval(): void
    {
        foreach ([false, true] as $google_only) {
            $this -> withAccount(function (User $user, array $pair) use ($google_only): void {
                if ($google_only) {
                    DB::run('UPDATE `Users` SET `passwordHash` = ? WHERE `userId` = ?', 'si', '', $user -> userId);
                }
                FediverseDelivery::enqueue($user -> userId, ['type' => 'Create'], ['https://old.invalid/inbox']);
                User::delete($user -> userId);
                $this -> assertNull(User::load($user -> userId));
                $deliveries = self::deliveries($user);
                $this -> assertCount(2, $deliveries);
                foreach ($deliveries as $delivery) {
                    $this -> assertNull($delivery -> actorUserId);
                    $signing = DeletedActor::signingKey($delivery);
                    $this -> assertNotNull($signing);
                    $this -> assertSame(ActivityPubActor::keyIdFor($user), $signing['keyId']);
                    openssl_sign($delivery -> activity, $signature, $signing['privateKey'], OPENSSL_ALGO_SHA256);
                    $this -> assertSame(1, openssl_verify($delivery -> activity, $signature, $pair['publicKeyPem'], OPENSSL_ALGO_SHA256));
                }
                $document = DeletedActor::document($user -> slug);
                $this -> assertTrue($document['suspended']);
                $this -> assertSame($pair['publicKeyPem'], $document['publicKey']['publicKeyPem']);
                foreach (['email', 'summary', 'name', 'icon', 'privateKey', 'encryptedPrivateKey', 'passwordHash'] as $field) {
                    $this -> assertFalse(isset($document[$field]));
                }
                $this -> assertCount(0, DB::rows('SELECT `deliveryId` FROM `FediverseDeliveries` WHERE `actorUserId` = ?', 'stdClass', 'i', $user -> userId));
            });
        }
    }

    public function testFailedDeliveryRetainsIdentityAndFinalSuccessPurgesIt(): void
    {
        $this -> withAccount(function (User $user): void {
            User::delete($user -> userId);
            $deliveries = self::deliveries($user);
            $this -> assertFalse(FediverseDelivery::failed($deliveries[0] -> deliveryId, 0, 'offline'));
            $this -> assertNotNull(DeletedActor::signingKey($deliveries[0]));
            FediverseDelivery::succeeded($deliveries[1] -> deliveryId);
            $this -> assertNotNull(DeletedActor::document($user -> slug));
            FediverseDelivery::succeeded($deliveries[0] -> deliveryId);
            $this -> assertNull(DeletedActor::document($user -> slug));
            $this -> assertNull(DeletedActor::signingKey($deliveries[0]));
        });
    }

    public function testSevenDayDeadlineRemovesSigningMaterialAndPendingJobs(): void
    {
        $this -> withAccount(function (User $user): void {
            User::delete($user -> userId);
            $deliveries = self::deliveries($user);
            $row = DB::row('SELECT TIMESTAMPDIFF(SECOND, NOW(), `expiresAt`) AS `remaining` FROM `DeletedActors` WHERE `slug` = ?', 'stdClass', 's', $user -> slug);
            $this -> assertTrue((int) $row -> remaining > 6 * 86400 && (int) $row -> remaining <= 7 * 86400);
            DB::run('UPDATE `DeletedActors` SET `expiresAt` = NOW() - INTERVAL 1 SECOND WHERE `slug` = ?', 's', $user -> slug);
            $this -> assertNull(DeletedActor::signingKey($deliveries[0]));
            $this -> assertNull(DeletedActor::document($user -> slug));
            DeletedActor::prune();
            foreach ($deliveries as $delivery) {
                $this -> assertNull(DB::row('SELECT `deliveryId` FROM `FediverseDeliveries` WHERE `deliveryId` = ?', 'stdClass', 'i', $delivery -> deliveryId));
            }
            $this -> assertNull(DB::row('SELECT `deletedActorId` FROM `DeletedActors` WHERE `slug` = ?', 'stdClass', 's', $user -> slug));
        });
    }

    public function testRetainedKeyCannotSignOtherActivitiesOrOtherActors(): void
    {
        $this -> withAccount(function (User $user): void {
            User::delete($user -> userId);
            $delivery = self::deliveries($user)[0];
            $activity = json_decode($delivery -> activity, true);
            foreach (['actor', 'object', 'type'] as $field) {
                $forged = clone $delivery;
                $forged -> activity = json_encode(array_replace($activity, [$field => 'forged']));
                $this -> assertNull(DeletedActor::signingKey($forged));
            }
            $delivery -> actorUserId = 1;
            $this -> assertNull(DeletedActor::signingKey($delivery));
        });
    }

    public function testDeletionRollbackRestoresAccountAndLeavesNoDetachedJobOrKey(): void
    {
        $this -> withAccount(function (User $user): void {
            try {
                DB::transaction(static function () use ($user): void {
                    $method = new \ReflectionMethod(User::class, 'deleteRecords');
                    $method -> invoke(null, $user -> userId);
                    throw new \RuntimeException('simulated deletion failure');
                });
            } catch (\RuntimeException $exception) {
                $this -> assertSame('simulated deletion failure', $exception -> getMessage());
            }
            $this -> assertNotNull(User::load($user -> userId));
            $this -> assertCount(0, self::deliveries($user));
            $this -> assertNull(DeletedActor::document($user -> slug));
            $this -> assertCount(2, FediverseFollower::deliveryInboxesFor($user -> userId));
        });
    }
}
