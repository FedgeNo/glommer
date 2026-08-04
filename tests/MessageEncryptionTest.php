<?php

declare(strict_types=1);

class MessageEncryptionTest extends TestCase
{
    /**
     * Builds a valid envelope the way MessageCrypto.js does: the body under a
     * random per-message key, that key wrapped under a conversation key, GCM
     * tags appended to both ciphertexts.
     *
     * @return array{envelope: string, messageKey: string, conversationKey: string}
     */
    private function envelope(string $plaintext): array
    {
        $message_key = random_bytes(32);
        $conversation_key = random_bytes(32);

        $iv = random_bytes(12);
        $ct = openssl_encrypt($plaintext, 'aes-256-gcm', $message_key, OPENSSL_RAW_DATA, $iv, $ct_tag, '', 16);

        $kiv = random_bytes(12);
        $wk = openssl_encrypt($message_key, 'aes-256-gcm', $conversation_key, OPENSSL_RAW_DATA, $kiv, $wk_tag, '', 16);

        $envelope = (string) json_encode([
            'v' => 1,
            'iv' => base64_encode($iv),
            'ct' => base64_encode($ct . $ct_tag),
            'kiv' => base64_encode($kiv),
            'wk' => base64_encode($wk . $wk_tag),
        ]);

        return ['envelope' => $envelope, 'messageKey' => $message_key, 'conversationKey' => $conversation_key];
    }

    public function testAValidEnvelopeNormalizes(): void
    {
        $this -> assertNotNull(MessageEnvelope::normalize($this -> envelope('hello')['envelope']));
    }

    public function testNormalizingDropsFieldsThatAreNotTheEnvelopes(): void
    {
        $fields = json_decode($this -> envelope('hello')['envelope'], true);
        $fields['ride_along'] = 'data';

        $normalized = MessageEnvelope::normalize((string) json_encode($fields));

        $this -> assertNotNull($normalized);
        $this -> assertFalse(array_key_exists('ride_along', json_decode($normalized, true)));
    }

    public function testMalformedEnvelopesAreRejected(): void
    {
        $valid = json_decode($this -> envelope('hello')['envelope'], true);

        $wrong_version = $valid;
        $wrong_version['v'] = 2;

        $missing_field = $valid;
        unset($missing_field['wk']);

        $bad_base64 = $valid;
        $bad_base64['iv'] = 'not base64!!!';

        $short_nonce = $valid;
        $short_nonce['iv'] = base64_encode(random_bytes(8));

        $oversized_body = $valid;
        $oversized_body['ct'] = base64_encode(random_bytes(65535 + 17));

        $this -> assertNull(MessageEnvelope::normalize('not json'));
        $this -> assertNull(MessageEnvelope::normalize((string) json_encode($wrong_version)));
        $this -> assertNull(MessageEnvelope::normalize((string) json_encode($missing_field)));
        $this -> assertNull(MessageEnvelope::normalize((string) json_encode($bad_base64)));
        $this -> assertNull(MessageEnvelope::normalize((string) json_encode($short_nonce)));
        $this -> assertNull(MessageEnvelope::normalize((string) json_encode($oversized_body)));
    }

    public function testARevealedKeyOpensTheEnvelope(): void
    {
        $built = $this -> envelope('the reported message');

        $this -> assertSame('the reported message', MessageEnvelope::decryptWithKey($built['envelope'], $built['messageKey']));
    }

    public function testTheWrongKeyOpensNothing(): void
    {
        $built = $this -> envelope('the reported message');

        $this -> assertNull(MessageEnvelope::decryptWithKey($built['envelope'], random_bytes(32)));
        $this -> assertNull(MessageEnvelope::decryptWithKey($built['envelope'], 'short'));
    }

    public function testATamperedCiphertextOpensNothing(): void
    {
        $built = $this -> envelope('the reported message');
        $fields = json_decode($built['envelope'], true);
        $ct = base64_decode($fields['ct'], true);
        $ct[0] = chr(ord($ct[0]) ^ 1);
        $fields['ct'] = base64_encode($ct);

        $this -> assertNull(MessageEnvelope::decryptWithKey((string) json_encode($fields), $built['messageKey']));
    }

    public function testFrankingTagsVerifyAndBindToTheirRow(): void
    {
        if (!MessageFranking::isConfigured()) {
            return;
        }

        $envelope = $this -> envelope('hello')['envelope'];
        $tag = MessageFranking::tag(5, 9, $envelope);

        $this -> assertNotNull($tag);
        $this -> assertTrue(MessageFranking::verify(5, 9, $envelope, $tag));

        // A different ciphertext, or the same one claimed between different
        // people, is a different message - the tag must not carry over.
        $this -> assertFalse(MessageFranking::verify(5, 9, $this -> envelope('hello')['envelope'], $tag));
        $this -> assertFalse(MessageFranking::verify(9, 5, $envelope, $tag));
        $this -> assertFalse(MessageFranking::verify(5, 10, $envelope, $tag));
    }
}
