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
