<?php

declare(strict_types=1);

/**
 * What both browsers have to agree on before they can talk to each other, and
 * the one promise this feature makes: media never passes through a server.
 */
class VideoCallTest extends TestCase
{
    public function testNoRelayIsEverOffered(): void
    {
        // A TURN entry is what would carry the video through a server. Its
        // absence is the whole guarantee - a pair with no direct path between
        // them gets no call rather than a proxied one.
        foreach (VideoCall::iceServers() as $server) {
            $this -> assertTrue(str_starts_with($server['urls'], 'stun:'), 'only STUN may be offered, got ' . $server['urls']);
        }
    }

    public function testMoreThanOneStunEndpointIsOffered(): void
    {
        // They answer the same question, so one being unreachable should not
        // cost the call.
        $this -> assertTrue(count(VideoCall::iceServers()) > 1);
    }

    public function testOnlyKnownSignalKindsAreAccepted(): void
    {
        foreach (['probeOffer', 'probeAnswer', 'offer', 'answer', 'candidate', 'decline', 'hangup'] as $type) {
            $this -> assertTrue(VideoCall::isSignalType($type), $type . ' should be a signal the relay carries');
        }
    }

    public function testAnUnknownSignalKindIsRefused(): void
    {
        $this -> assertFalse(VideoCall::isSignalType('subscribe'));
        $this -> assertFalse(VideoCall::isSignalType(''));
    }

    public function testExactlyOneSideOpensTheProbe(): void
    {
        // Both browsers run identical code, so without this they would both
        // offer and neither answer.
        $this -> assertTrue(VideoCall::initiates(4, 9));
        $this -> assertFalse(VideoCall::initiates(9, 4));
    }
}
