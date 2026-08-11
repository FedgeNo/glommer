<?php

declare(strict_types=1);

/**
 * What the environment checks say when something is wrong with the server
 * itself, in Portuguese. See src/locales/en/Diagnostics.php for what this
 * file is.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Não foi possível ligar ao servidor WebSocket em 127.0.0.1:{port}{tls} ({error}). Inicia-o primeiro: systemctl --user start glommer-websocket (ver o README.md para o ficheiro de unidade). Se acabou de ganhar ou perder WS_TLS_CERT/WS_TLS_KEY, reinicia-o para corresponder ao .env.',
        'wsOverTLS' => ' via TLS',
        'wsBadHandshake' => 'O serviço em 127.0.0.1:{port} não completou um handshake WebSocket válido - pode haver outra coisa, que não bin/websocket-server.php, à escuta nessa porta.',
        'wsNoPong' => 'O servidor WebSocket aceitou o handshake mas não respondeu a um ping - pode estar bloqueado ou a funcionar mal. Verifica os registos.',
        'wsReachable' => 'Servidor WebSocket acessível e a responder em 127.0.0.1:{port}',
    ],
];
