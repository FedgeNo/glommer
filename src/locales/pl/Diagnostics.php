<?php

declare(strict_types=1);

/**
 * Polish for what the environment checks say when something is wrong with the
 * server itself. See src/locales/en/Diagnostics.php.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => 'Nie udało się połączyć z serwerem WebSocket pod adresem 127.0.0.1:{port}{tls} ({error}). Najpierw go uruchom: systemctl --user start glommer-websocket (plik jednostki opisano w README.md). Jeśli właśnie zyskał lub stracił WS_TLS_CERT/WS_TLS_KEY, zrestartuj go, aby był zgodny z .env.',
        'wsOverTLS' => ' przez TLS',
        'wsBadHandshake' => 'Usługa pod adresem 127.0.0.1:{port} nie zakończyła prawidłowo uzgadniania WebSocket - być może na tym porcie nasłuchuje coś innego niż bin/websocket-server.php.',
        'wsNoPong' => 'Serwer WebSocket zaakceptował uzgadnianie, ale nie odpowiedział na ping - może być zawieszony lub działać nieprawidłowo. Sprawdź jego dzienniki.',
        'wsReachable' => 'Serwer WebSocket jest dostępny i odpowiada pod adresem 127.0.0.1:{port}',
    ],
];
