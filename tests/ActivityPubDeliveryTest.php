<?php

declare(strict_types=1);

class ActivityPubDeliveryTest extends TestCase
{
    /** @return array{body: string, headers: string[]} */
    private function signedRequest(string $url, array $activity, array $keypair): array
    {
        $method = new \ReflectionMethod(ActivityPubDelivery::class, 'signedRequest');
        $request = $method -> invoke(null, $url, $activity, 'https://local.invalid/actor#main-key', $keypair['privateKeyPem']);

        $this -> assertNotNull($request);

        return $request;
    }

    /** @return array<string, string> */
    private function headers(array $lines): array
    {
        $headers = [];

        foreach ($lines as $line) {
            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower($name)] = trim($value);
        }

        return $headers;
    }

    public function testAnInboxWithoutARequestPathIsRefusedBeforeSending(): void
    {
        $this -> assertFalse(ActivityPubDelivery::post(
            'https://remote.invalid',
            ['type' => 'Create'],
            'https://local.invalid/actor#main-key',
            'not-needed'
        ));
    }

    public function testAnActivityThatCannotBeEncodedIsRefusedBeforeSending(): void
    {
        $this -> assertFalse(ActivityPubDelivery::post(
            'https://remote.invalid/inbox',
            ['type' => 'Create', 'actor' => "\xB1\x31"],
            'https://local.invalid/actor#main-key',
            'not-needed'
        ));
    }

    public function testARemoteActorCannotSignADeliveryAsOneOfOurMembers(): void
    {
        $author = new User();
        $author -> remoteActorURI = 'https://remote.invalid/users/someone';

        $this -> assertFalse(ActivityPubDelivery::postAs(
            $author,
            'https://remote.invalid/inbox',
            ['type' => 'Create']
        ));
    }

    public function testTheDeliveredBytesMatchTheirDigestAndSignature(): void
    {
        $keypair = ActivityPubKeys::generateKeypair();
        $request = $this -> signedRequest(
            'https://remote.invalid/inbox?tenant=one',
            ['type' => 'Create', 'id' => 'https://local.invalid/activities/1'],
            $keypair
        );
        $headers = $this -> headers($request['headers']);

        $this -> assertSame('{"type":"Create","id":"https://local.invalid/activities/1"}', $request['body']);
        $this -> assertTrue(HTTPSignature::bodyMatchesDigest($request['body'], $headers['digest']));
        $this -> assertTrue(HTTPSignature::verify(
            'POST',
            '/inbox?tenant=one',
            $headers,
            $headers['signature'],
            $keypair['publicKeyPem']
        ));
    }

    public function testDeliveryAdvertisesActivityPubInBothDirections(): void
    {
        $request = $this -> signedRequest(
            'https://remote.invalid/inbox',
            ['type' => 'Follow'],
            ActivityPubKeys::generateKeypair()
        );
        $headers = $this -> headers($request['headers']);

        $this -> assertSame('application/activity+json', $headers['content-type']);
        $this -> assertSame('application/activity+json', $headers['accept']);
        $this -> assertSame('remote.invalid', $headers['host']);
        $this -> assertTrue(strtotime($headers['date']) !== false);
    }
}
