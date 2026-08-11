<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/MessagingAndStatus.php for what this fragment
 * covers.
 */

return [
    'MessageKeySetupForm' => [
        'resetWarning' => 'パスフレーズを忘れましたか? リセットすると新しいパスフレーズで新しい鍵が作られます - ただし、古い鍵で暗号化されたメッセージは誰であっても二度と読めなくなります。',
        'requirements' => '12文字以上で、アカウントのパスワードとは別のものにしてください - パスワードはこのサーバーに送信されますが、パスフレーズは決して送信してはいけません。',
        'passphraseLabel' => 'パスフレーズ',
        'resetPassphraseLabel' => '新しいパスフレーズ',
        'confirmLabel' => 'パスフレーズ(確認)',
        'accountPasswordLabel' => 'アカウントのパスワード',
        'submitLabel' => '暗号化メッセージを有効にする',
        'resetSubmitLabel' => '暗号鍵をリセット',
    ],

    'EncryptedMessagesSetting' => [
        'explanation' => 'エンドツーエンドで暗号化されたメッセージは、ブラウザ内でロック・解除されます: このサーバーは中継と保存を行うだけで、内容を読むことはできません。鍵はパスフレーズで保護されており、同じパスフレーズならどのブラウザからでもメッセージを解除できます。会話は双方がこの機能を有効にすると暗号化されます。他のサーバーの相手とのメッセージは暗号化されないままです - フェディバースにはそれを運ぶ手段がないためです。',
        'noRecovery' => '失われたパスフレーズを復元する方法はありません - 管理者であっても不可能です。パスフレーズを失うことは、暗号化されたメッセージを失うことを意味します。',
        'enabledStatus' => '暗号化メッセージは有効です。',
    ],

    'MessagePrivacyButton' => [
        'encrypted' => [
            'label' => '🔒 暗号化済み',
            'explanation' => 'この会話のメッセージはエンドツーエンドで暗号化されています: それぞれのパスフレーズでブラウザ内で解除・表示され、このサーバーが保存しているのは暗号文だけです。誰も間に入っていないことを確かめるには、スレッド下部の安全コードを相手と照らし合わせてください。暗号化を有効にする前に送られたメッセージは、そのまま読める状態で残ります。',
        ],
        'awaiting-theirs' => [
            'label' => '🔓 未暗号化',
            'explanation' => '{handle}が設定で暗号化メッセージを有効にすると、ここでのメッセージはエンドツーエンドで暗号化されます。',
        ],
        'awaiting-yours' => [
            'label' => '🔓 未暗号化',
            'explanation' => 'ここでのメッセージはエンドツーエンドで暗号化されていません。この会話を保護するには、設定で暗号化メッセージを有効にしてください。',
        ],
        'federated' => [
            'label' => '🔓 未暗号化',
            'explanation' => '{handle}は他のサーバーのアカウントです。この会話のメッセージはこのサーバーだけでなくそのサーバーにも保存され、その管理者は内容を読むことができます - サーバー間のプロトコルには暗号化する手段がありません。機密性の高い内容は、このサイト内の会話だけにとどめてください。',
        ],
    ],

    'RemoteFollowsForm' => [
        'legend' => 'フェディバースのアカウントをフォロー',
        'notice' => 'ハンドルを1つ以上貼り付けてください。例: @user@example.social - 区切り文字は何でも構いません。',
        'handlesLabel' => 'フォローするフェディバースのハンドル',
        'submit' => 'フォロー',
        'statusPending' => '保留中',
        'statusAccepted' => '承認済み',
    ],

    'ServerBlockForm' => [
        'legend' => 'サーバーをブロック',
        'description' => 'そのサーバーおよびその配下からのやり取りをすべて拒否します: 配送の送受信は行われず、双方向の既存のフォローも解除されます。',
        'serverLabel' => 'サーバー',
        'serverPlaceholder' => 'example.social',
        'reasonLabel' => '理由',
        'reasonPlaceholder' => 'このサーバーをブロックする理由',
        'submit' => 'サーバーをブロック',
    ],

    'VideoCallTestPanel' => [
        'intro' => '通話設定のうち、1つのブラウザだけで確認できる部分を実行します。実際のピアツーピア接続の手前まではここでテストできますが、他の人との接続にはその相手が必要です。',
    ],

    'VideoCallTestButton' => [
        'label' => 'チェックを実行',
    ],

    'WebSocketStatus' => [
        'ok' => 'WebSocketサーバー: 稼働中',
        'failed' => 'WebSocketサーバー: {detail}',
        'clientTesting' => 'ブラウザの接続: 確認中…',
        'clientConnecting' => 'ブラウザの接続: 接続中…',
        'clientConnected' => 'ブラウザの接続: 接続済み',
        'clientDisconnecting' => 'ブラウザの接続: 切断中…',
        'clientNotConnected' => 'ブラウザの接続: 未接続',
    ],

    'UploadWorkerStatus' => [
        'running' => 'ワーカーサービス: 稼働中',
        'stopped' => 'ワーカーサービス: 停止中 - 再起動するまで、アップロード済みのファイルは変換されません',
        'unknown' => 'ワーカーサービス: 不明 - このホストでsystemctlが使えないか、SELinuxがウェブサーバー自身のステータス確認を拒否しています(rootでbin/install.phpを実行すると解決します)',
        'queue' => 'キュー: ステージング中{staging}件、保留中{pending}件、処理中{processing}件',
    ],

    'TrendingTimerStatus' => [
        'running' => 'トレンドタイマー: 稼働中',
        'stopped' => 'トレンドタイマー: 停止中 - トレンドトピックは読み取り時の自己修復(Trending::current())によってのみ更新され、定期更新は行われません。設定するにはrootでbin/install.phpを実行してください。',
        'unknown' => 'トレンドタイマー: 不明 - このホストでsystemctlが使えないか、SELinuxがウェブサーバー自身のステータス確認を拒否しています(rootでbin/install.phpを実行すると解決します)',
    ],
];
