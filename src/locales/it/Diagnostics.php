<?php

declare(strict_types=1);

/**
 * Italian for what the environment checks say when something is wrong with
 * the server itself. See src/locales/en/Diagnostics.php for what this
 * fragment covers.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Impossibile connettersi al server WebSocket su 127.0.0.1:{port}{tls} ({error}). Avvialo prima: systemctl --user start glommer-websocket (vedi README.md per il file di unit). Se ha appena guadagnato o perso WS_TLS_CERT/WS_TLS_KEY, riavvialo perché corrisponda a .env.',
        'wsOverTLS' => ' su TLS',
        'wsBadHandshake' => 'Il servizio su 127.0.0.1:{port} non ha completato un handshake WebSocket valido - qualcosa di diverso da bin/websocket-server.php potrebbe essere in ascolto su quella porta.',
        'wsNoPong' => 'Il server WebSocket ha accettato l\'handshake ma non ha risposto a un ping - potrebbe essersi bloccato o non funzionare correttamente. Controlla i suoi log.',
        'wsReachable' => 'Server WebSocket raggiungibile e che risponde su 127.0.0.1:{port}',
    ],
];
