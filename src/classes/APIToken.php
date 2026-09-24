<?php

declare(strict_types=1);

/** One revocable posting credential per local account. */
class APIToken
{
    public static function current(int $user_id): ?string
    {
        $row = DB::row('
SELECT `tokenCiphertext`
    FROM `APITokens`
    WHERE `userId` = ?
', \stdClass::class, 'i', $user_id);

        if ($row === null) {
            return null;
        }

        $token = ActivityPubKeys::decryptPrivateKey(
            (string) $row -> tokenCiphertext,
            (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '')
        );

        if ($token === null) {
            throw new \RuntimeException('The posting token could not be decrypted.');
        }

        return $token;
    }

    public static function rotate(int $user_id): string
    {
        $token = 'glom_' . rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '=');
        $hash = hash('sha256', $token);
        $ciphertext = ActivityPubKeys::encryptPrivateKey(
            $token,
            (string) Env::get('ACTIVITYPUB_ENCRYPTION_KEY', '')
        );

        $changed = DB::transaction(static function () use ($user_id, $hash, $ciphertext): bool {
            $account = DB::row('SELECT `isBot` FROM `Users` WHERE `userId` = ? FOR UPDATE', \stdClass::class, 'i', $user_id);
            DB::run('
INSERT INTO `APITokens` (`userId`, `tokenHash`, `tokenCiphertext`)
    VALUES (?, ?, ?)
    ON DUPLICATE KEY UPDATE `tokenHash` = VALUES(`tokenHash`), `tokenCiphertext` = VALUES(`tokenCiphertext`)
', 'iss', $user_id, $hash, $ciphertext);
            DB::run('UPDATE `Users` SET `isBot` = 1 WHERE `userId` = ?', 'i', $user_id);

            return $account !== null && (int) $account -> isBot !== 1;
        });

        if ($changed) {
            self::announceActorChange($user_id);
        }

        return $token;
    }

    public static function revoke(int $user_id): void
    {
        $changed = DB::transaction(static function () use ($user_id): bool {
            $account = DB::row('SELECT `isBot` FROM `Users` WHERE `userId` = ? FOR UPDATE', \stdClass::class, 'i', $user_id);
            DB::run('DELETE FROM `APITokens` WHERE `userId` = ?', 'i', $user_id);
            DB::run('UPDATE `Users` SET `isBot` = 0 WHERE `userId` = ?', 'i', $user_id);

            return $account !== null && (int) $account -> isBot === 1;
        });

        if ($changed) {
            self::announceActorChange($user_id);
        }
    }

    private static function announceActorChange(int $user_id): void
    {
        $user = User::load($user_id);

        if ($user !== null) {
            FediversePublisher::profileUpdated($user);
        }
    }

    public static function userForBearer(string $header): ?User
    {
        if (preg_match('/\ABearer (glom_[A-Za-z0-9_-]{43})\z/i', $header, $matches) !== 1) {
            return null;
        }

        $hash = hash('sha256', $matches[1]);

        return DB::row('
SELECT `Users`.*
    FROM `APITokens`
    JOIN `Users` ON `Users`.`userId` = `APITokens`.`userId`
    WHERE `APITokens`.`tokenHash` = ?
        AND `Users`.`remoteActorURI` IS NULL
        AND `Users`.`banned` = 0
        AND `Users`.`verified` = 1
', User::class, 's', $hash);
    }
}
