<?php

declare(strict_types=1);

/**
 * Read-only WebSocket-daemon health for the admin Site Settings page. Reuses
 * EnvironmentChecker::checkWebSocketServer() - a real handshake + ping/pong
 * round trip against bin/websocket-server.php, not a `systemctl is-active`
 * shell-out - so this tells "genuinely serving connections" from "systemd
 * thinks it's up but it's actually wedged/misconfigured", and sidesteps the
 * SELinux status-query denial UploadWorkerStatus has to work around entirely
 * (a live socket connect needs no shell-out, so there's no policy query to deny).
 */
class WebSocketStatus extends Div
{
    public ?string $class = 'Card d-flex flex-column gap-2 WebSocketStatus';

    public function toDOM(): \DOMElement
    {
        $check = EnvironmentChecker::checkWebSocketServer();

        $status_line = new Paragraph('WebSocket server: ' . ($check['ok'] ? 'Running' : $check['message']));

        if (!$check['ok']) {
            $status_line -> class = 'Error';
        }

        $this -> contents[] = $status_line;

        return parent::toDOM();
    }
}
