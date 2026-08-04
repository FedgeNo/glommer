<?php

declare(strict_types=1);

/**
 * A signature is valid for as long as its Date header is fresh, so anyone who
 * captured one delivery could post it again inside that window and have it
 * accepted - it is, after all, the same request signed by the same server.
 * Having seen it before is the only thing that tells the two apart.
 */
class ActivityPubReplayTest extends DatabaseTestCase
{
    private function signature(): string
    {
        return 'keyId="https://remote.example/actor#main-key",signature="' . base64_encode(random_bytes(32)) . '"';
    }

    public function testTheFirstDeliveryIsNotAReplay(): void
    {
        $this -> assertFalse(ActivityPubReplay::seenBefore($this -> signature()));
    }

    public function testTheSameSignatureAgainIsAReplay(): void
    {
        $signature = $this -> signature();

        $this -> assertFalse(ActivityPubReplay::seenBefore($signature));
        $this -> assertTrue(ActivityPubReplay::seenBefore($signature));
        $this -> assertTrue(ActivityPubReplay::seenBefore($signature));
    }

    public function testTwoDifferentDeliveriesBothGoThrough(): void
    {
        $this -> assertFalse(ActivityPubReplay::seenBefore($this -> signature()));
        $this -> assertFalse(ActivityPubReplay::seenBefore($this -> signature()));
    }
}
