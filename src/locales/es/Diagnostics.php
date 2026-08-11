<?php

declare(strict_types=1);

/**
 * Spanish. See src/locales/en/Diagnostics.php for what this fragment covers.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'No se pudo conectar con el servidor WebSocket en 127.0.0.1:{port}{tls} ({error}). Inícialo primero: systemctl --user start glommer-websocket (consulta README.md para ver el archivo de unidad). Si acaba de ganar o perder WS_TLS_CERT/WS_TLS_KEY, reinícialo para que coincida con .env.',
        'wsOverTLS' => ' por TLS',
        'wsBadHandshake' => 'El servicio en 127.0.0.1:{port} no completó un protocolo de enlace WebSocket válido: puede que algo distinto de bin/websocket-server.php esté escuchando en ese puerto.',
        'wsNoPong' => 'El servidor WebSocket aceptó el protocolo de enlace pero no respondió a un ping: puede estar bloqueado o funcionando mal. Consulta sus registros.',
        'wsReachable' => 'Servidor WebSocket accesible y respondiendo en 127.0.0.1:{port}',
    ],
];
