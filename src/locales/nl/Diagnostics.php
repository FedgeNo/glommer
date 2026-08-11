<?php

declare(strict_types=1);

/**
 * Dutch for what the environment checks say. See
 * src/locales/en/Diagnostics.php for the source and the shape each entry is
 * built to.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Kan geen verbinding maken met de WebSocket-server op 127.0.0.1:{port}{tls} ({error}). Start deze eerst: systemctl --user start glommer-websocket (zie README.md voor het unit-bestand). Als de server net WS_TLS_CERT/WS_TLS_KEY heeft gekregen of verloren, herstart hem dan zodat hij overeenkomt met .env.',
        'wsOverTLS' => ' via TLS',
        'wsBadHandshake' => 'De service op 127.0.0.1:{port} heeft geen geldige WebSocket-handshake voltooid - mogelijk luistert er iets anders dan bin/websocket-server.php op die poort.',
        'wsNoPong' => 'De WebSocket-server heeft de handshake geaccepteerd maar reageerde niet op een ping - hij kan vastzitten of zich verkeerd gedragen. Controleer de logs.',
        'wsReachable' => 'WebSocket-server bereikbaar en reageert op 127.0.0.1:{port}',
    ],
];
