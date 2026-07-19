<?php

declare(strict_types=1);

class ActivityPubKeysTest extends TestCase
{
    public function testGeneratesADistinctRSAKeypair(): void
    {
        $keypair = ActivityPubKeys::generateKeypair();

        $this -> assertTrue(str_contains($keypair['publicKeyPem'], 'PUBLIC KEY'));
        $this -> assertTrue(str_contains($keypair['privateKeyPem'], 'PRIVATE KEY'));

        $other = ActivityPubKeys::generateKeypair();
        $this -> assertTrue($keypair['privateKeyPem'] !== $other['privateKeyPem']);
    }

    public function testEncryptDecryptRoundTrips(): void
    {
        $keypair = ActivityPubKeys::generateKeypair();
        $encryption_key_hex = bin2hex(random_bytes(32));

        $encrypted = ActivityPubKeys::encryptPrivateKey($keypair['privateKeyPem'], $encryption_key_hex);

        $this -> assertTrue($encrypted !== $keypair['privateKeyPem']);
        $this -> assertSame($keypair['privateKeyPem'], ActivityPubKeys::decryptPrivateKey($encrypted, $encryption_key_hex));
    }

    public function testWrongEncryptionKeyFailsToDecrypt(): void
    {
        $keypair = ActivityPubKeys::generateKeypair();
        $encrypted = ActivityPubKeys::encryptPrivateKey($keypair['privateKeyPem'], bin2hex(random_bytes(32)));

        $this -> assertNull(ActivityPubKeys::decryptPrivateKey($encrypted, bin2hex(random_bytes(32))));
    }

    /**
     * OpenSSL silently zero-pads an undersized key rather than refusing it,
     * so a blank or truncated ACTIVITYPUB_ENCRYPTION_KEY would otherwise
     * "encrypt" the signing key under all zeros with no visible symptom.
     */
    public function testRefusesToEncryptWithAKeyThatIsNotExactly32Bytes(): void
    {
        $private_key_pem = ActivityPubKeys::generateKeypair()['privateKeyPem'];

        foreach (['', 'aabb', bin2hex(random_bytes(16)), bin2hex(random_bytes(64)), str_repeat('z', 64)] as $bad_key) {
            $threw = false;

            try {
                ActivityPubKeys::encryptPrivateKey($private_key_pem, $bad_key);
            } catch (\RuntimeException $exception) {
                $threw = true;
            }

            $this -> assertTrue($threw);
        }
    }

    public function testRefusesToDecryptWithAKeyThatIsNotExactly32Bytes(): void
    {
        $keypair = ActivityPubKeys::generateKeypair();
        $encrypted = ActivityPubKeys::encryptPrivateKey($keypair['privateKeyPem'], bin2hex(random_bytes(32)));

        foreach (['', 'aabb', bin2hex(random_bytes(16)), str_repeat('z', 64)] as $bad_key) {
            $this -> assertNull(ActivityPubKeys::decryptPrivateKey($encrypted, $bad_key));
        }
    }

    public function testTruncatedCiphertextFailsToDecrypt(): void
    {
        $encryption_key_hex = bin2hex(random_bytes(32));

        $this -> assertNull(ActivityPubKeys::decryptPrivateKey(base64_encode('short'), $encryption_key_hex));
        $this -> assertNull(ActivityPubKeys::decryptPrivateKey('not valid base64 !!!', $encryption_key_hex));
    }

    public function testTamperedCiphertextFailsToDecrypt(): void
    {
        $keypair = ActivityPubKeys::generateKeypair();
        $encryption_key_hex = bin2hex(random_bytes(32));
        $encrypted = ActivityPubKeys::encryptPrivateKey($keypair['privateKeyPem'], $encryption_key_hex);

        $raw = base64_decode($encrypted, true);
        $raw[strlen($raw) - 1] = chr((ord($raw[strlen($raw) - 1]) + 1) % 256);
        $tampered = base64_encode($raw);

        $this -> assertNull(ActivityPubKeys::decryptPrivateKey($tampered, $encryption_key_hex));
    }
}
