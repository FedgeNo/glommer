<?php

declare(strict_types=1);

/**
 * Web Push (RFC 8030/8291/8292), hand-rolled like the rest of the stack: the
 * payload sealed with aes128gcm under keys agreed by ECDH with the browser,
 * the request vouched for by a VAPID ES256 token, delivered to whatever push
 * service the browser named when it subscribed.
 *
 * Nothing here runs during a web request. Notification::create() enqueues a
 * row per subscription and the federation worker drains them - a push
 * service having a slow afternoon must never be something a reply waits on.
 * Pushes are news: one retry, then the row is dropped, and an endpoint that
 * answers 404/410 takes its whole subscription with it (the browser revoked
 * it; the push service is telling us so).
 */
class WebPush
{
    /** One retry, minutes later - after that the news is stale. */
    private const RETRY_DELAY_SECONDS = 300;

    private const MAX_ATTEMPTS = 2;

    /** Same claim lease as FediverseDelivery::CLAIM_SECONDS, same reasoning. */
    private const CLAIM_SECONDS = 600;

    /** Push services cap payloads at 4KB; ours are a title and a URL. */
    private const RECORD_SIZE = 4096;

    /**
     * Queues one push per subscription the member holds. The payload is what
     * the service worker shows: a line of text and where tapping it goes.
     */
    public static function enqueueFor(int $user_id, string $text, string $url): void
    {
        if (!WebPushKeys::isConfigured()) {
            return;
        }

        $payload = (string) json_encode(['text' => $text, 'url' => $url], JSON_UNESCAPED_SLASHES);

        DB::run('
INSERT INTO `PushDeliveries` (`pushSubscriptionId`, `payload`)
    SELECT `pushSubscriptionId`, ?
        FROM `PushSubscriptions`
        WHERE `userId` = ?
', 'si', $payload, $user_id);
    }

    /**
     * Drains due rows - the federation worker's per-pass call. The batch is
     * claimed with the same expiring lease FediverseDelivery::due() stamps,
     * so overlapping workers never push the same notification twice.
     */
    public static function deliverDue(int $limit = 20): void
    {
        $lease_seconds = self::CLAIM_SECONDS;

        $due = DB::transaction(static function () use ($limit, $lease_seconds): array {
            $rows = DB::rows('
SELECT `PushDeliveries`.*, `PushSubscriptions`.`endpoint`, `PushSubscriptions`.`p256dh`, `PushSubscriptions`.`auth`
    FROM `PushDeliveries`
    JOIN `PushSubscriptions` ON `PushSubscriptions`.`pushSubscriptionId` = `PushDeliveries`.`pushSubscriptionId`
    WHERE `PushDeliveries`.`nextAttemptAt` <= NOW() AND (`PushDeliveries`.`claimedUntil` IS NULL OR `PushDeliveries`.`claimedUntil` <= NOW())
    ORDER BY `PushDeliveries`.`nextAttemptAt`, `PushDeliveries`.`pushDeliveryId`
    LIMIT ' . max(1, $limit) . '
    FOR UPDATE SKIP LOCKED
', \stdClass::class);

            foreach ($rows as $row) {
                DB::run('
UPDATE `PushDeliveries`
    SET `claimedUntil` = NOW() + INTERVAL ? SECOND
    WHERE `pushDeliveryId` = ?
', 'ii', $lease_seconds, (int) $row -> pushDeliveryId);
            }

            return $rows;
        });

        foreach ($due as $delivery) {
            self::attempt($delivery);
        }
    }

    private static function attempt(object $delivery): void
    {
        $sent = self::send(
            (string) $delivery -> endpoint,
            (string) $delivery -> p256dh,
            (string) $delivery -> auth,
            (string) $delivery -> payload
        );

        if ($sent) {
            self::dropDelivery((int) $delivery -> pushDeliveryId);

            return;
        }

        $status = SafeHTTPFetcher::lastResponseStatus();

        // The push service says this endpoint is gone: the browser revoked
        // the subscription, and every future push to it would be this same
        // failure. The subscription's other queued rows cascade away with it.
        if ($status === 404 || $status === 410) {
            DB::run('
DELETE
    FROM `PushSubscriptions`
    WHERE `pushSubscriptionId` = ?
', 'i', (int) $delivery -> pushSubscriptionId);

            return;
        }

        if ((int) $delivery -> attempts + 1 >= self::MAX_ATTEMPTS) {
            self::dropDelivery((int) $delivery -> pushDeliveryId);

            return;
        }

        // The claim is released with the reschedule, so the retry is
        // anyone's to take when it comes due.
        DB::run('
UPDATE `PushDeliveries`
    SET `attempts` = `attempts` + 1, `nextAttemptAt` = NOW() + INTERVAL ? SECOND, `claimedUntil` = NULL
    WHERE `pushDeliveryId` = ?
', 'ii', self::RETRY_DELAY_SECONDS, (int) $delivery -> pushDeliveryId);
    }

    private static function dropDelivery(int $push_delivery_id): void
    {
        DB::run('
DELETE
    FROM `PushDeliveries`
    WHERE `pushDeliveryId` = ?
', 'i', $push_delivery_id);
    }

    /** One sealed, vouched-for POST to the subscription's push service. */
    public static function send(string $endpoint, string $p256dh, string $auth, string $payload): bool
    {
        $sealed = self::seal($p256dh, $auth, $payload);
        $vapid = self::vapidHeader($endpoint);

        if ($sealed === null || $vapid === null) {
            return false;
        }

        return SafeHTTPFetcher::post($endpoint, $sealed, [
            'Authorization: ' . $vapid,
            'Content-Encoding: aes128gcm',
            'Content-Type: application/octet-stream',
            'TTL: 86400',
            'Urgency: normal',
        ], 4096) !== null;
    }

    /**
     * RFC 8291: an ephemeral P-256 agreement against the browser's
     * subscription key, HKDF down to one AES-128-GCM key and nonce, the
     * whole thing framed as a single aes128gcm record with the ephemeral
     * public key riding in the header.
     */
    private static function seal(string $p256dh, string $auth, string $payload): ?string
    {
        $ua_public_raw = WebPushKeys::base64urlDecode($p256dh);
        $auth_secret = WebPushKeys::base64urlDecode($auth);

        if ($ua_public_raw === null || strlen($ua_public_raw) !== 65 || $auth_secret === null || strlen($auth_secret) !== 16) {
            return null;
        }

        $ephemeral = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);

        if ($ephemeral === false) {
            return null;
        }

        $ephemeral_details = openssl_pkey_get_details($ephemeral);

        if ($ephemeral_details === false || !isset($ephemeral_details['ec']['x'], $ephemeral_details['ec']['y'])) {
            return null;
        }

        // openssl strips leading zero bytes off the coordinates; the wire
        // format is fixed-width 32 each, and one short byte shifts the whole
        // frame.
        $as_public_raw = "\x04"
            . str_pad($ephemeral_details['ec']['x'], 32, "\x00", STR_PAD_LEFT)
            . str_pad($ephemeral_details['ec']['y'], 32, "\x00", STR_PAD_LEFT);

        $ua_key = openssl_pkey_get_public(self::pemFromPoint($ua_public_raw));

        if ($ua_key === false) {
            return null;
        }

        $ecdh_secret = openssl_pkey_derive($ua_key, $ephemeral);

        if ($ecdh_secret === false) {
            return null;
        }

        // RFC 8291 §3.3-3.4: auth secret and the two public keys bind the
        // agreement to this exact subscription, then the content keys.
        $ikm = hash_hkdf('sha256', $ecdh_secret, 32, 'WebPush: info' . "\x00" . $ua_public_raw . $as_public_raw, $auth_secret);

        $salt = random_bytes(16);
        $cek = hash_hkdf('sha256', $ikm, 16, 'Content-Encoding: aes128gcm' . "\x00", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, 'Content-Encoding: nonce' . "\x00", $salt);

        // 0x02 marks the final (only) record's padding boundary.
        $ciphertext = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);

        if ($ciphertext === false) {
            return null;
        }

        $header = $salt . pack('N', self::RECORD_SIZE) . chr(65) . $as_public_raw;

        return $header . $ciphertext . $tag;
    }

    /**
     * RFC 8292: an ES256 token naming the push service's origin, signed with
     * the site's VAPID key, its public half riding alongside so the service
     * can check the signature against what the browser subscribed with.
     */
    private static function vapidHeader(string $endpoint): ?string
    {
        $private_pem = WebPushKeys::privateKeyPem();
        $public_for_browser = WebPushKeys::publicKeyForBrowser();

        if ($private_pem === null || $public_for_browser === null) {
            return null;
        }

        $scheme = (string) parse_url($endpoint, PHP_URL_SCHEME);
        $host = (string) parse_url($endpoint, PHP_URL_HOST);

        if ($scheme === '' || $host === '') {
            return null;
        }

        $claims = [
            'aud' => $scheme . '://' . $host,
            'exp' => time() + 43200,
            'sub' => (string) Config::get('siteURL'),
        ];

        $signing_input = WebPushKeys::base64url((string) json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            . '.' . WebPushKeys::base64url((string) json_encode($claims, JSON_UNESCAPED_SLASHES));

        if (!openssl_sign($signing_input, $der_signature, $private_pem, OPENSSL_ALGO_SHA256)) {
            return null;
        }

        $raw_signature = self::derToRawSignature($der_signature);

        if ($raw_signature === null) {
            return null;
        }

        return 'vapid t=' . $signing_input . '.' . WebPushKeys::base64url($raw_signature) . ', k=' . $public_for_browser;
    }

    /** A raw 65-byte P-256 point wrapped as the PEM openssl wants to receive. */
    private static function pemFromPoint(string $point): string
    {
        $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $point;

        return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
    }

    /**
     * ES256's JOSE form is r||s, each padded to 32 bytes; openssl emits DER.
     */
    private static function derToRawSignature(string $der): ?string
    {
        // SEQUENCE { INTEGER r, INTEGER s } - minimal parse, since openssl
        // wrote it and the shape is fixed.
        $offset = 2;

        if (strlen($der) < 8 || $der[0] !== "\x30") {
            return null;
        }

        // A long-form sequence length (>127 bytes) never happens for P-256.
        $parts = [];

        for ($i = 0; $i < 2; $i++) {
            if (($der[$offset] ?? '') !== "\x02") {
                return null;
            }

            $length = ord($der[$offset + 1]);
            $integer = substr($der, $offset + 2, $length);
            $offset += 2 + $length;

            // Strip a sign byte, pad back to 32.
            $integer = ltrim($integer, "\x00");

            if (strlen($integer) > 32) {
                return null;
            }

            $parts[] = str_pad($integer, 32, "\x00", STR_PAD_LEFT);
        }

        return $parts[0] . $parts[1];
    }
}
