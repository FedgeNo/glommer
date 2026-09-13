<?php

declare(strict_types=1);

require_once __DIR__ . '/WebSocketFixture.php';

class WebSocketDaemonTest extends TestCase
{
    public function testHealthProbeChecksTransportWithoutBorrowingAnAccountIdentity(): void
    {
        $daemon = new WebSocketFixture();
        try {
            $this -> assertTrue(EnvironmentChecker::checkWebSocketServer()['ok']);
            $this -> assertSame(0, $daemon -> push(1));
        } finally {
            $daemon -> close();
        }
    }

    public function testOnlyAuthenticatedInternalControlDisconnectsTheIntendedSessions(): void
    {
        $daemon = new WebSocketFixture();
        try {
            $token = WSToken::issue(7, 2, str_repeat('a', 64));
            $victim = $daemon -> connect($token);
            $daemon -> connect(WSToken::issue(7, 2, str_repeat('b', 64)));
            $attacker = $daemon -> connect(WSToken::issue(8, 2, str_repeat('c', 64)));
            $daemon -> waitForClients(7, 2);
            $daemon -> waitForClients(8, 1);
            $command = WSRevocation::issue(7, 'session', str_repeat('a', 64));
            $forged = json_decode($command, true);
            $forged['signature'] = str_repeat('0', 64);
            $daemon -> control(json_encode($forged));
            $this -> assertSame(2, $daemon -> push(7));

            // Even a genuine signed command sent on the public socket is not
            // a control request. It can only invalidate the sender's own socket.
            $daemon -> text($attacker, $command);
            $daemon -> waitForClients(8, 0);
            $this -> assertSame(2, $daemon -> push(7));
            $this -> assertSame(2, $daemon -> push(7, json_decode($command, true)));
            $this -> assertSame(2, $daemon -> push(7));

            $this -> assertSame(true, $daemon -> control($command)['accepted'] ?? false);
            $this -> assertSame(1, $daemon -> push(7));
            $daemon -> connect($token); // old, still correctly signed lease cannot reconnect
            usleep(20000);
            $this -> assertSame(1, $daemon -> push(7));
            $daemon -> connect(WSToken::issue(7, 2, str_repeat('d', 64)));
            $daemon -> waitForClients(7, 2);
            $this -> assertFalse($daemon -> control($command)['accepted'] ?? false);
            $this -> assertSame(2, $daemon -> push(7));

            $daemon -> connect(WSToken::issue(7, 3, str_repeat('e', 64)));
            $daemon -> waitForClients(7, 3);
            $daemon -> control(WSRevocation::issue(7, 'version', 3));
            $this -> assertSame(1, $daemon -> push(7), 'a new login survives an older generation revocation');
        } finally {
            $daemon -> close();
        }
    }

    public function testExpiryAndRenewalAreEnforcedEvenWhenNoRevocationWasDelivered(): void
    {
        $daemon = new WebSocketFixture();
        try {
            $session = str_repeat('a', 64);
            $expiring = WSToken::issue(7, 2, $session, null, time() - 27);
            $renewed = $daemon -> connect($expiring);
            $daemon -> connect(WSToken::issue(8, 2, $session, null, time() - 27));
            $daemon -> waitForClients(7, 1);
            $daemon -> waitForClients(8, 1);
            $renewal = WSToken::issue(7, 2, $session);
            $split = intdiv(strlen($renewal), 2);
            $daemon -> text($renewed, substr($renewal, 0, $split), 1, false);
            $daemon -> text($renewed, substr($renewal, $split), 0);
            // Bounded real expiry check; normal test startup need not wait 30 seconds.
            sleep(3);
            $this -> assertSame(0, $daemon -> push(8));
            $this -> assertSame(1, $daemon -> push(7));
            $daemon -> text($renewed, WSToken::issue(9, 2, $session));
            $daemon -> waitForClients(7, 0);
            $this -> assertSame(0, $daemon -> push(9), 'renewal cannot transfer socket identity');
        } finally {
            $daemon -> close();
        }
    }
}
