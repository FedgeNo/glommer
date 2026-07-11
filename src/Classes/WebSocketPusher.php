<?php

declare(strict_types=1);

/**
 * Fire-and-forget bridge from a normal Apache/PHP-FPM request into the
 * separate long-running bin/websocket-server.php process, which holds the
 * actual open connections. A short-lived TCP connection to its internal
 * push port carries one JSON line; the daemon fans it out to every open
 * connection for that user and is never allowed to block or fail the
 * calling HTTP request - if the daemon isn't running, this just no-ops.
 */
class WebSocketPusher
{
    public static function push(int $user_id, array $payload): void
    {
        $config = require __DIR__ . '/../config.php';

        $socket = @stream_socket_client(
            'tcp://127.0.0.1:' . $config['wsPushPort'],
            $error_code,
            $error_message,
            0.2
        );

        if ($socket === false) {
            return;
        }

        stream_set_timeout($socket, 1);

        @fwrite($socket, json_encode([
            'secret' => $config['wsSecret'],
            'userId' => $user_id,
            'payload' => $payload,
        ]) . "\n");

        @fgets($socket); // drain the daemon's response so its write doesn't block on a full buffer
        @fclose($socket);
    }
}
