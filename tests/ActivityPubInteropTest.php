<?php

declare(strict_types=1);

class ActivityPubInteropTest extends DatabaseTestCase
{
    public function testRemoteDocumentsAndDeferredDeliveries(): void
    {
        // A separate process replaces only HTTP and WebSocket transports. The
        // actual parsers, inbox handlers and queue use a fresh schema/database;
        // no remote messages or notifications can escape this fixture.
        $process = proc_open([PHP_BINARY, __DIR__ . '/fixtures/federation-fetch.php'], [
            0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1],
        ], $pipes);
        $this -> assertTrue(is_resource($process));
        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $this -> assertSame(0, proc_close($process), $output);
        $this -> assertTrue(str_contains($output, 'All federation fixture cases passed'), $output);
    }
}
