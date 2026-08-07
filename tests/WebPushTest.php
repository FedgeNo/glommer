<?php

declare(strict_types=1);

/**
 * Web Push, held to what it must never get wrong: the queue is per
 * subscription, a creating notification enqueues without sending, the
 * RFC 8291 seal round-trips against a real browser keypair, and a VAPID
 * keypair stored encrypted comes back usable.
 */
class WebPushTest extends DatabaseTestCase
{
    private function configureKeys(): void
    {
        // A throwaway .env-style secret and a fresh VAPID pair under it - the
        // same path the installer takes, so privateKeyPem() has something to
        // decrypt.
        putenv('ACTIVITYPUB_ENCRYPTION_KEY=' . str_repeat('ab', 32));

        $keypair = WebPushKeys::generateKeypair();
        Settings::set(WebPushKeys::PRIVATE_SETTING, ActivityPubKeys::encryptPrivateKey($keypair['privateKeyPem'], str_repeat('ab', 32)));
        Settings::set(WebPushKeys::PUBLIC_SETTING, $keypair['publicKeyPem']);
    }

    private function subscribe(int $user_id): array
    {
        // A stand-in "browser": a P-256 key it would have generated, and the
        // 16-byte auth secret, both stored the base64url way the real
        // subscribe endpoint stores them.
        $browser = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        $details = openssl_pkey_get_details($browser);
        $public = "\x04"
            . str_pad($details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($details['ec']['y'], 32, "\x00", STR_PAD_LEFT);
        $auth = random_bytes(16);

        DB::run('
INSERT INTO `PushSubscriptions` (`userId`, `endpoint`, `p256dh`, `auth`)
    VALUES (?, ?, ?, ?)
', 'isss', $user_id, 'https://push.example/' . bin2hex(random_bytes(6)),
            WebPushKeys::base64url($public), WebPushKeys::base64url($auth));

        return ['browser' => $browser, 'public' => $public, 'auth' => $auth];
    }

    private function queueDepth(): int
    {
        $row = DB::row('
SELECT COUNT(*) AS `total`
    FROM `PushDeliveries`
', 'PostCountData');

        return (int) $row -> total;
    }

    public function testAKeypairStoredEncryptedComesBackUsable(): void
    {
        $this -> configureKeys();

        $this -> assertTrue(WebPushKeys::isConfigured());
        $this -> assertNotNull(WebPushKeys::privateKeyPem());
        // The browser-facing key is the 65-byte uncompressed point, base64url.
        $this -> assertSame(65, strlen((string) WebPushKeys::base64urlDecode((string) WebPushKeys::publicKeyForBrowser())));
    }

    public function testCreatingANotificationEnqueuesOnePushPerSubscription(): void
    {
        $this -> configureKeys();

        $recipient = self::createUser();
        $this -> subscribe($recipient);
        $this -> subscribe($recipient);

        $before = $this -> queueDepth();
        Notification::create($recipient, self::createUser(), 'like', 1);

        // One per subscription, and the request that created the notification
        // did not send anything - it only queued.
        $this -> assertSame($before + 2, $this -> queueDepth());
    }

    public function testNoKeypairMeansNothingIsEnqueued(): void
    {
        // The whole feature is gated on WebPushKeys::isConfigured().
        putenv('ACTIVITYPUB_ENCRYPTION_KEY=' . str_repeat('ab', 32));
        Settings::set(WebPushKeys::PRIVATE_SETTING, '');
        Settings::set(WebPushKeys::PUBLIC_SETTING, '');

        $recipient = self::createUser();
        $this -> subscribe($recipient);

        $before = $this -> queueDepth();
        Notification::create($recipient, self::createUser(), 'like', 1);

        $this -> assertSame($before, $this -> queueDepth());
    }

    public function testTheSealedPayloadDecryptsWithTheBrowsersKeys(): void
    {
        $this -> configureKeys();

        $subscriber = $this -> subscribe(self::createUser());
        $plaintext = '{"text":"someone liked your post","url":"https://glommer.test/notifications"}';

        $sealed = (new \ReflectionMethod(WebPush::class, 'seal')) -> invoke(
            null,
            WebPushKeys::base64url($subscriber['public']),
            WebPushKeys::base64url($subscriber['auth']),
            $plaintext
        );

        $this -> assertNotNull($sealed);
        $this -> assertSame($plaintext, self::openSeal($sealed, $subscriber['browser'], $subscriber['auth'], $subscriber['public']));
    }

    /** The receiver's half of RFC 8291, to prove the sender's half. */
    private static function openSeal(string $sealed, \OpenSSLAsymmetricKey $browser, string $auth, string $ua_public): string
    {
        $salt = substr($sealed, 0, 16);
        $as_public = substr($sealed, 21, 65);
        $ciphertext = substr($sealed, 86);

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode(hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $as_public), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        $secret = openssl_pkey_derive(openssl_pkey_get_public($pem), $browser);
        $ikm = hash_hkdf('sha256', $secret, 32, 'WebPush: info' . "\x00" . $ua_public . $as_public, $auth);
        $cek = hash_hkdf('sha256', $ikm, 16, 'Content-Encoding: aes128gcm' . "\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, 'Content-Encoding: nonce' . "\x00", $salt);

        $tag = substr($ciphertext, -16);
        $body = substr($ciphertext, 0, -16);

        return rtrim((string) openssl_decrypt($body, 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag), "\x02");
    }
}
