<?php

declare(strict_types=1);

/**
 * Signing what we fetch. Instances in secure mode refuse to serve an actor or an
 * object to an unsigned request, and the refusal looks exactly like the account
 * not existing - so getting this wrong makes whole servers silently invisible.
 */
class ActivityPubFetchTest extends TestCase
{
    /** @var array{publicKeyPem: string, privateKeyPem: string} */
    private static array $pair;

    private static function pair(): array
    {
        self::$pair ??= ActivityPubKeys::generateKeypair();

        return self::$pair;
    }

    private static function header(string $path = '/users/bob/', string $host = 'example.test', string $date = 'Mon, 01 Jan 2035 00:00:00 GMT'): string
    {
        return HTTPSignature::signGet($path, $host, $date, 'https://us.test/activitypub/actor#main-key', self::pair()['privateKeyPem']);
    }

    public function testASignedFetchVerifiesAgainstItsOwnKey(): void
    {
        $date = 'Mon, 01 Jan 2035 00:00:00 GMT';
        $header = self::header('/users/bob/', 'example.test', $date);

        preg_match('/signature="([^"]+)"/', $header, $matches);

        // The string a verifier on the far side would rebuild.
        $signing_string = "(request-target): get /users/bob/\nhost: example.test\ndate: " . $date;

        $this -> assertSame(1, openssl_verify($signing_string, base64_decode($matches[1]), self::pair()['publicKeyPem'], OPENSSL_ALGO_SHA256));
    }

    public function testAFetchNeverClaimsToCoverADigest(): void
    {
        // A GET has no body. Signing a digest over nothing is a claim about a
        // body that does not exist, and servers checking coverage reject it.
        $this -> assertFalse(str_contains(self::header(), 'digest'));
    }

    public function testAFetchCoversTheTargetTheHostAndTheDate(): void
    {
        $fields = HTTPSignature::parseSignatureHeader(self::header());

        $this -> assertNotNull($fields);
        $this -> assertSame('(request-target) host date', $fields['headers']);
    }

    public function testTheKeyIdNamesTheInstanceActor(): void
    {
        // Signed as the instance rather than as a member: the far side learns
        // that this server is looking, not which of its members is reading them.
        $fields = HTTPSignature::parseSignatureHeader(self::header());

        $this -> assertSame('https://us.test/activitypub/actor#main-key', $fields['keyId']);
    }

    public function testADifferentPathProducesADifferentSignature(): void
    {
        // Otherwise a captured signature would authorise fetching anything.
        $this -> assertFalse(self::header('/users/bob/') === self::header('/users/eve/'));
    }

    public function testADifferentHostProducesADifferentSignature(): void
    {
        $this -> assertFalse(self::header('/users/bob/', 'example.test') === self::header('/users/bob/', 'elsewhere.test'));
    }

    public function testASignatureDoesNotVerifyForAnotherPath(): void
    {
        $date = 'Mon, 01 Jan 2035 00:00:00 GMT';
        $header = self::header('/users/bob/', 'example.test', $date);

        preg_match('/signature="([^"]+)"/', $header, $matches);

        $wrong_target = "(request-target): get /users/eve/\nhost: example.test\ndate: " . $date;

        $this -> assertFalse(openssl_verify($wrong_target, base64_decode($matches[1]), self::pair()['publicKeyPem'], OPENSSL_ALGO_SHA256) === 1);
    }
}
