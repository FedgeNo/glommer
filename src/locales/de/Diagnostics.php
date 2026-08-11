<?php

declare(strict_types=1);

/**
 * German for what the environment checks say. See
 * src/locales/en/Diagnostics.php for the source and the shape each entry is
 * built to.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Konnte nicht mit dem WebSocket-Server auf 127.0.0.1:{port}{tls} verbinden ({error}). Starte ihn zuerst: systemctl --user start glommer-websocket (die Unit-Datei steht in README.md). Falls WS_TLS_CERT/WS_TLS_KEY gerade hinzugekommen oder weggefallen sind, starte ihn neu, damit er zur .env passt.',
        'wsOverTLS' => ' über TLS',
        'wsBadHandshake' => 'Der Dienst auf 127.0.0.1:{port} hat keinen gültigen WebSocket-Handshake abgeschlossen - möglicherweise lauscht dort etwas anderes als bin/websocket-server.php auf diesem Port.',
        'wsNoPong' => 'Der WebSocket-Server hat den Handshake akzeptiert, aber nicht auf einen Ping geantwortet - er könnte hängen oder sich falsch verhalten. Prüfe seine Logs.',
        'wsReachable' => 'WebSocket-Server erreichbar und antwortet auf 127.0.0.1:{port}',
    ],
];
