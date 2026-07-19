<?php

declare(strict_types=1);

class HTTPSignatureTest extends TestCase
{
    private function keypair(): array
    {
        return ActivityPubKeys::generateKeypair();
    }

    /** @return array<string, string> */
    private function headers(string $digest, ?string $date = null, array $extra = []): array
    {
        return array_merge([
            'host' => 'remote.example',
            'date' => $date ?? gmdate('D, d M Y H:i:s') . ' GMT',
            'digest' => $digest,
        ], $extra);
    }

    public function testSignThenVerifyRoundTrips(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('{"type":"Follow"}');
        $headers = $this -> headers($digest);

        $header = HTTPSignature::sign('POST', '/inbox', $headers['host'], $headers['date'], $digest, 'https://glommer.test/actor#main-key', $keypair['privateKeyPem']);

        $this -> assertTrue(HTTPSignature::verify('POST', '/inbox', $headers, $header, $keypair['publicKeyPem']));
    }

    public function testVerifyFailsWithTheWrongPublicKey(): void
    {
        $keypair = $this -> keypair();
        $other_keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $headers = $this -> headers($digest);

        $header = HTTPSignature::sign('POST', '/inbox', $headers['host'], $headers['date'], $digest, 'keyid', $keypair['privateKeyPem']);

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $headers, $header, $other_keypair['publicKeyPem']));
    }

    public function testVerifyFailsIfTheDigestWasTamperedWithAfterSigning(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('original body');
        $headers = $this -> headers($digest);

        $header = HTTPSignature::sign('POST', '/inbox', $headers['host'], $headers['date'], $digest, 'keyid', $keypair['privateKeyPem']);

        $tampered = $this -> headers(HTTPSignature::digest('a different body entirely'), $headers['date']);

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $tampered, $header, $keypair['publicKeyPem']));
    }

    public function testVerifyFailsIfTheMethodOrPathChanged(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $headers = $this -> headers($digest);

        $header = HTTPSignature::sign('POST', '/inbox', $headers['host'], $headers['date'], $digest, 'keyid', $keypair['privateKeyPem']);

        $this -> assertFalse(HTTPSignature::verify('POST', '/other-inbox', $headers, $header, $keypair['publicKeyPem']));
    }

    public function testVerifyFailsIfTheHostDiffersFromTheOneSigned(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $headers = $this -> headers($digest);

        $header = HTTPSignature::sign('POST', '/inbox', 'somewhere-else.example', $headers['date'], $digest, 'keyid', $keypair['privateKeyPem']);

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $headers, $header, $keypair['publicKeyPem']));
    }

    /**
     * Senders sign different header sets in different orders; a valid
     * signature covering MORE than the minimum has to be accepted, or real
     * traffic is refused for no security benefit.
     */
    public function testAcceptsASignatureCoveringExtraHeadersInAnyOrder(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $headers = $this -> headers($digest, null, ['content-type' => 'application/activity+json', 'user-agent' => 'SomeServer/1.0']);

        $covered = 'date host (request-target) digest content-type';
        $signing_string = "date: {$headers['date']}\nhost: {$headers['host']}\n(request-target): post /inbox\ndigest: {$digest}\ncontent-type: {$headers['content-type']}";

        openssl_sign($signing_string, $signature, $keypair['privateKeyPem'], OPENSSL_ALGO_SHA256);
        $header = 'keyId="k",algorithm="rsa-sha256",headers="' . $covered . '",signature="' . base64_encode($signature) . '"';

        $this -> assertTrue(HTTPSignature::verify('POST', '/inbox', $headers, $header, $keypair['publicKeyPem']));
    }

    /**
     * The flexibility above must not become a downgrade: dropping any of the
     * four required headers from the covered set is refused even when the
     * signature over that shorter set is itself perfectly valid.
     */
    public function testRefusesASignatureThatOmitsAnyRequiredHeader(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $headers = $this -> headers($digest);

        $omissions = [
            'host date digest' => "host: {$headers['host']}\ndate: {$headers['date']}\ndigest: {$digest}",
            '(request-target) date digest' => "(request-target): post /inbox\ndate: {$headers['date']}\ndigest: {$digest}",
            '(request-target) host digest' => "(request-target): post /inbox\nhost: {$headers['host']}\ndigest: {$digest}",
            '(request-target) host date' => "(request-target): post /inbox\nhost: {$headers['host']}\ndate: {$headers['date']}",
        ];

        foreach ($omissions as $covered => $signing_string) {
            openssl_sign($signing_string, $signature, $keypair['privateKeyPem'], OPENSSL_ALGO_SHA256);
            $header = 'keyId="k",algorithm="rsa-sha256",headers="' . $covered . '",signature="' . base64_encode($signature) . '"';

            $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $headers, $header, $keypair['publicKeyPem']));
        }
    }

    public function testRefusesASignatureNamingAHeaderThatWasNotReceived(): void
    {
        $keypair = $this -> keypair();
        $digest = HTTPSignature::digest('body');
        $headers = $this -> headers($digest);

        $covered = '(request-target) host date digest x-absent';
        $signing_string = "(request-target): post /inbox\nhost: {$headers['host']}\ndate: {$headers['date']}\ndigest: {$digest}\nx-absent: anything";

        openssl_sign($signing_string, $signature, $keypair['privateKeyPem'], OPENSSL_ALGO_SHA256);
        $header = 'keyId="k",algorithm="rsa-sha256",headers="' . $covered . '",signature="' . base64_encode($signature) . '"';

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $headers, $header, $keypair['publicKeyPem']));
    }

    public function testRefusesANonRSASHA256Algorithm(): void
    {
        $keypair = $this -> keypair();
        $header = 'keyId="k",algorithm="hs2019",headers="(request-target) host date digest",signature="' . base64_encode('x') . '"';

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $this -> headers('d'), $header, $keypair['publicKeyPem']));
    }

    public function testRejectsAMalformedSignatureHeader(): void
    {
        $keypair = $this -> keypair();

        $this -> assertFalse(HTTPSignature::verify('POST', '/inbox', $this -> headers('d'), 'not a signature header', $keypair['publicKeyPem']));
    }

    public function testDateFreshnessBoundsReplay(): void
    {
        $this -> assertTrue(HTTPSignature::dateIsFresh(gmdate('D, d M Y H:i:s') . ' GMT'));
        $this -> assertTrue(HTTPSignature::dateIsFresh(gmdate('D, d M Y H:i:s', time() - 600) . ' GMT'));
        $this -> assertFalse(HTTPSignature::dateIsFresh(gmdate('D, d M Y H:i:s', time() - 10800) . ' GMT'));
        $this -> assertFalse(HTTPSignature::dateIsFresh(gmdate('D, d M Y H:i:s', time() + 10800) . ' GMT'));
        $this -> assertFalse(HTTPSignature::dateIsFresh('not a date'));
    }
}
