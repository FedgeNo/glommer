<?php

declare(strict_types=1);

/** Minimal, expiring signing identity for an account already removed locally. */
class DeletedActor
{
    public const RETENTION_DAYS = 7;

    /** Called inside User::delete's transaction, before followers and keys disappear. */
    public static function enqueue(User $author, array $activity): void
    {
        $inboxes = array_values(array_unique(array_filter(array_merge(
            FediverseFollower::deliveryInboxesFor((int) $author -> userId),
            Relay::deliveryInboxes()
        ), static fn (string $url): bool => $url !== ''
            && !ActivityPubActor::isLocalActorURI($url) && !RemoteServer::isBlockedURL($url))));

        if ($inboxes === []) {
            return;
        }

        if (!$author -> actorEncryptedPrivateKey || !$author -> actorPublicKeyPem) {
            ActivityPubActor::privateKeyPem($author);
        }
        if (!$author -> actorEncryptedPrivateKey || !$author -> actorPublicKeyPem) {
            throw new \RuntimeException('Cannot preserve the account deletion signing identity.');
        }

        DB::run('
INSERT INTO `DeletedActors` (`slug`, `actorURI`, `publicKeyPem`, `encryptedPrivateKey`, `expiresAt`)
    VALUES (?, ?, ?, ?, NOW() + INTERVAL ? DAY)
', 'ssssi', (string) $author -> slug, ActivityPubActor::uriFor($author),
            $author -> actorPublicKeyPem, $author -> actorEncryptedPrivateKey, self::RETENTION_DAYS);
        $id = (int) mysqli_insert_id(DB::connection());
        $body = json_encode($activity, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        foreach ($inboxes as $inbox) {
            DB::run('
INSERT INTO `FediverseDeliveries` (`deletedActorId`, `inboxURL`, `activity`)
    VALUES (?, ?, ?)
', 'iss', $id, $inbox, $body);
        }
    }

    /** Expiry also removes the dependent deliveries, even if the worker was down. */
    public static function prune(): void
    {
        DB::run('DELETE FROM `DeletedActors` WHERE `expiresAt` <= NOW()');
        DB::run('
DELETE FROM `DeletedActors`
    WHERE NOT EXISTS (SELECT 1 FROM `FediverseDeliveries` WHERE `FediverseDeliveries`.`deletedActorId` = `DeletedActors`.`deletedActorId`)
');
    }

    public static function removeIfFinished(int $id): void
    {
        DB::run('
DELETE FROM `DeletedActors` WHERE `deletedActorId` = ?
    AND NOT EXISTS (SELECT 1 FROM `FediverseDeliveries` WHERE `deletedActorId` = ?)
', 'ii', $id, $id);
    }

    /** @return array{keyId: string, privateKey: string}|null */
    public static function signingKey(FediverseDeliveryData $delivery): ?array
    {
        if ($delivery -> deletedActorId === null || $delivery -> actorUserId !== null) {
            return null;
        }
        $row = DB::row('
SELECT `actorURI`, `encryptedPrivateKey` FROM `DeletedActors`
    WHERE `deletedActorId` = ? AND `expiresAt` > NOW()
', 'stdClass', 'i', $delivery -> deletedActorId);
        $activity = json_decode((string) $delivery -> activity, true);
        // This key is usable only for the account's own Delete, never other
        // queued work or a caller-selected actor/object.
        if ($row === null || !is_array($activity) || ($activity['type'] ?? null) !== 'Delete'
            || ($activity['actor'] ?? null) !== $row -> actorURI || ($activity['object'] ?? null) !== $row -> actorURI) {
            return null;
        }
        $key = ActivityPubKeys::decryptPrivateKey($row -> encryptedPrivateKey, (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', ''));
        return $key === null ? null : ['keyId' => $row -> actorURI . '#main-key', 'privateKey' => $key];
    }

    /**
     * Remote inboxes may refresh a signing key while processing the Delete.
     * Expose only the inactive identity and public key, never profile content.
     * The human profile remains absent. No account or login can be restored here.
     */
    public static function document(string $slug): ?array
    {
        $row = DB::row('
SELECT `slug`, `actorURI`, `publicKeyPem` FROM `DeletedActors`
    WHERE `slug` = ? AND `expiresAt` > NOW()
', 'stdClass', 's', $slug);
        if ($row === null) {
            return null;
        }
        return [
            '@context' => ['https://www.w3.org/ns/activitystreams', 'https://w3id.org/security/v1',
                ['suspended' => 'http://joinmastodon.org/ns#suspended']],
            'id' => $row -> actorURI,
            'type' => 'Person',
            'preferredUsername' => $row -> slug,
            'inbox' => $row -> actorURI . 'inbox',
            'suspended' => true,
            'publicKey' => [
                'id' => $row -> actorURI . '#main-key',
                'owner' => $row -> actorURI,
                'publicKeyPem' => $row -> publicKeyPem,
            ],
        ];
    }
}
