<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/Diagnostics.php for what this fragment covers.
 */

return [
    'EnvironmentChecker' => [
        'wsCannotConnect' => '127.0.0.1:{port}{tls} のWebSocketサーバーに接続できませんでした({error})。まず起動してください: systemctl --user start glommer-websocket(ユニットファイルについてはREADME.mdを参照)。WS_TLS_CERT/WS_TLS_KEYが追加または削除された直後であれば、.envと一致するよう再起動してください。',
        'wsOverTLS' => '(TLS)',
        'wsBadHandshake' => '127.0.0.1:{port} のサービスは、有効なWebSocketハンドシェイクを完了しませんでした - bin/websocket-server.php以外の何かがそのポートで待ち受けている可能性があります。',
        'wsNoPong' => 'WebSocketサーバーはハンドシェイクを受け入れましたが、pingに応答しませんでした - 動作が止まっているか、不具合が起きている可能性があります。ログを確認してください。',
        'wsReachable' => 'WebSocketサーバーは127.0.0.1:{port}で到達可能で、正常に応答しています',
    ],
];
