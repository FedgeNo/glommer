<?php

declare(strict_types=1);

/**
 * French for what the environment checks say when something is wrong with the
 * server itself. See src/locales/en/Diagnostics.php.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Impossible de se connecter au serveur WebSocket sur 127.0.0.1:{port}{tls} ({error}). Démarrez-le d\'abord : systemctl --user start glommer-websocket (voir le README.md pour le fichier d\'unité). S\'il vient de gagner ou de perdre WS_TLS_CERT/WS_TLS_KEY, redémarrez-le pour qu\'il corresponde à .env.',
        'wsOverTLS' => ' via TLS',
        'wsBadHandshake' => 'Le service sur 127.0.0.1:{port} n\'a pas mené à bien une négociation WebSocket valide - autre chose que bin/websocket-server.php écoute peut-être sur ce port.',
        'wsNoPong' => 'Le serveur WebSocket a accepté la négociation mais n\'a pas répondu à un ping - il est peut-être bloqué ou ne se comporte pas normalement. Consultez ses journaux.',
        'wsReachable' => 'Serveur WebSocket joignable et répondant sur 127.0.0.1:{port}',
    ],
];
