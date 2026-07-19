<?php

declare(strict_types=1);

/**
 * WebSocket-daemon health for the admin Site Settings page, from two
 * different vantage points:
 *   - Server-side: EnvironmentChecker::checkWebSocketServer() - a real
 *     handshake + ping/pong round trip against bin/websocket-server.php run
 *     from the web server itself, not a `systemctl is-active` shell-out - so
 *     this tells "genuinely serving connections" from "systemd thinks it's
 *     up but it's actually wedged/misconfigured", and sidesteps the SELinux
 *     status-query denial UploadWorkerStatus has to work around entirely (a
 *     live socket connect needs no shell-out, so there's no policy query to
 *     deny).
 *   - Client-side: a real `wss://` connection attempt from the ADMIN'S OWN
 *     BROWSER, run by main.js against the .WebSocketClientStatus placeholder
 *     below. The server-side check alone can't catch a problem that only
 *     bites an actual outside client - a firewall/security-group rule that
 *     blocks WSPort from the public internet while allowing localhost, or a
 *     TLS certificate a real browser's trust store rejects even though PHP's
 *     stream context (verify_peer disabled) never would.
 */
class WebSocketStatus extends Div
{
    public ?string $class = 'Card d-flex flex-column gap-2 WebSocketStatus';

    public function toDOM(): \DOMElement
    {
        $check = EnvironmentChecker::checkWebSocketServer();

        $server_line = new Paragraph('WebSocket server: ' . ($check['ok'] ? 'Running' : $check['message']));

        if (!$check['ok']) {
            $server_line -> class = 'Error';
        }

        $this -> contents[] = $server_line;

        $client_line = new Paragraph('Browser connection: Testing…');
        $client_line -> class = 'WebSocketClientStatus';
        $this -> contents[] = $client_line;

        return parent::toDOM();
    }
}
