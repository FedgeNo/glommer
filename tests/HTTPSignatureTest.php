<?php

declare(strict_types=1);

class HTTPSignatureTest extends TestCase
{
    private function keypair(): array
    {
        return ActivityPubKeys::generateKeypair();
    }

    public function testSignThenVerifyRoundTrips(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('{"type":"Follow"}');
        $date = gmdate('D, d M Y H:i:s T');

        $header = HTTPSignature::sign('POST', '/inbox', 'remote.example', $date, $digest, 'https://glommer.test/actor#main-key', $keypair['privateKeyPem']);

        $this -> assertTrue(HTTPSignature::verify('POST', '/inbox', 'remote.example', $date, $digest, $header, $keypair['publicKeyPem']));
    }

    public function testVerifyFailsWithTheWrongPublicKey(): void
    {
        $keypair = $this -> keypair();
        $other_keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $date = gmdate('D, d M Y H:i:s T');

        $header = HTTPSignature::sign('POST', '/inbox', 'remote.example', $date, $digest, 'keyid', $keypair['privateKeyPem']);

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', 'remote.example', $date, $digest, $header, $other_keypair['publicKeyPem']));
    }

    public function testVerifyFailsIfTheDigestWasTamperedWithAfterSigning(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('original body');
        $date = gmdate('D, d M Y H:i:s T');

        $header = HTTPSignature::sign('POST', '/inbox', 'remote.example', $date, $digest, 'keyid', $keypair['privateKeyPem']);

        $tampered_digest = HTTPSignature::digest('a different body entirely');

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', 'remote.example', $date, $tampered_digest, $header, $keypair['publicKeyPem']));
    }

    public function testVerifyFailsIfTheMethodOrPathChanged(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $date = gmdate('D, d M Y H:i:s T');

        $header = HTTPSignature::sign('POST', '/inbox', 'remote.example', $date, $digest, 'keyid', $keypair['privateKeyPem']);

        $this -> assertFalse(HTTPSignature::verify('POST', '/other-inbox', 'remote.example', $date, $digest, $header, $keypair['publicKeyPem']));
    }

    public function testRejectsASignatureCoveringADifferentHeaderSet(): void
    {
        $keypair = $this -> keypair();

        $forged_header = 'keyId="k",algorithm="rsa-sha256",headers="date",signature="' . base64_encode('anything') . '"';

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', 'remote.example', 'date', 'digest', $forged_header, $keypair['publicKeyPem']));
    }

    public function testRejectsAMalformedSignatureHeader(): void
    {
        $keypair = $this -> keypair();

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', 'remote.example', 'date', 'digest', 'not a signature header', $keypair['publicKeyPem']));
    }
}
