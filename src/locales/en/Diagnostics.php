<?php

declare(strict_types=1);

/**
 * What the environment checks say when something is wrong with the server
 * itself. Read by an administrator rather than by a visitor, but read in
 * whatever language they set the site to like everything else.
 *
 * The parts that are not words - a port, an errno, the name of a unit file -
 * ride in as {tokens} rather than being concatenated around the sentence, so a
 * language can put them where its own grammar wants them.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Could not connect to the WebSocket server on 127.0.0.1:{port}{tls} ({error}). Start it first: systemctl --user start glommer-websocket (see README.md for the unit file). If it just gained or lost WS_TLS_CERT/WS_TLS_KEY, restart it so it matches .env.',
        // Appended to the address above only when the daemon is listening over
        // TLS. Its own entry because it is words, not a flag.
        'wsOverTLS' => ' over TLS',
        'wsBadHandshake' => 'The service on 127.0.0.1:{port} did not complete a valid WebSocket handshake - something other than bin/websocket-server.php may be listening on that port.',
        'wsNoPong' => 'The WebSocket server accepted the handshake but did not respond to a ping - it may be stuck or misbehaving. Check its logs.',
        'wsReachable' => 'WebSocket server reachable and responding on 127.0.0.1:{port}',
    ],
];
