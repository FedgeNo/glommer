<?php

declare(strict_types=1);

require_once __DIR__ . '/WebSocketFixture.php';

class WebSocketAdmissionTest extends TestCase
{
    public function testAddressNormalizationAndSeparateInternalCapacity(): void
    {
        $this -> assertSame('192.0.2.1', WebSocketAdmission::peerIP('[::ffff:192.0.2.1]:1234'));
        $this -> assertSame('192.0.2.1', WebSocketAdmission::peerIP('192.0.2.1:1234'));
        $this -> assertSame('2001:db8::1', WebSocketAdmission::peerIP('[2001:db8::1]:1234'));
        $connections = array_fill(0, 500, ['kind' => 'client', 'userId' => 1, 'peerIP' => '192.0.2.1']);
        $this -> assertFalse(WebSocketAdmission::allows($connections, 'client', '192.0.2.2'));
        $this -> assertTrue(WebSocketAdmission::allows($connections, 'push', '127.0.0.1'));
        $pending = array_fill(0, 50, ['kind' => 'client', 'userId' => null, 'peerIP' => '192.0.2.1']);
        $this -> assertFalse(WebSocketAdmission::allows($pending, 'client', '192.0.2.2'));
    }

    public function testAccountCapRejectsTheNewConnectionAndStillAllowsRenewal(): void
    {
        $daemon = new WebSocketFixture(['WS_MAX_CONNECTIONS_PER_ACCOUNT' => '1']);
        try {
            $token = WSToken::issue(71, 1, str_repeat('a', 64));
            $existing = $daemon -> connect($token);
            $daemon -> waitForClients(71, 1);
            $refused = $daemon -> connect(WSToken::issue(71, 1, str_repeat('b', 64)));
            $close = fread($refused, 4);
            $this -> assertSame("\x88\x02" . pack('n', 1013), $close);
            $daemon -> text($existing, $token);
            $this -> assertSame(1, $daemon -> push(71));
            $daemon -> connect(WSToken::issue(72, 1, str_repeat('c', 64)));
            $daemon -> waitForClients(72, 1);
        } finally {
            $daemon -> close();
        }
    }

    public function testGlobalCapacityPreservesInternalRevocationAndCanBeRetried(): void
    {
        $daemon = new WebSocketFixture(['WS_MAX_CONNECTIONS' => '2']);
        try {
            $daemon -> connect(WSToken::issue(81, 1, str_repeat('a', 64)));
            $daemon -> waitForClients(81, 1);
            $daemon -> connect(WSToken::issue(82, 1, str_repeat('b', 64)));
            $daemon -> waitForClients(82, 1);
            try {
                $daemon -> connect(WSToken::issue(83, 1, str_repeat('c', 64)));
                $this -> assertTrue(false, 'The third public connection must be refused');
            } catch (\RuntimeException $exception) {
                $this -> assertTrue(str_contains($exception -> getMessage(), 'handshake'));
            }
            $this -> assertSame(1, $daemon -> push(81));
            $this -> assertSame(1, $daemon -> push(82));
            $this -> assertTrue($daemon -> control(WSRevocation::issue(81, 'session', str_repeat('a', 64)))['accepted']);
            $daemon -> waitForClients(81, 0);
            $daemon -> connect(WSToken::issue(83, 1, str_repeat('c', 64)));
            $daemon -> waitForClients(83, 1);
            $this -> assertSame(1, $daemon -> push(82));
        } finally {
            $daemon -> close();
        }
    }

    public function testUnauthenticatedConnectionsHaveTheirOwnPerIPLimit(): void
    {
        $daemon = new WebSocketFixture(['WS_MAX_PENDING_PER_IP' => '1']);
        try {
            $pending = $daemon -> pending();
            usleep(30000);
            $refused = $daemon -> pending();
            $this -> assertSame('', fread($refused, 1));
            $this -> assertTrue(feof($refused));
            $this -> assertSame(0, $daemon -> push(91));
            fclose($pending);
            usleep(30000);
            $daemon -> connect(WSToken::issue(91, 1, str_repeat('a', 64)));
            $daemon -> waitForClients(91, 1);
        } finally {
            $daemon -> close();
        }
    }
}
