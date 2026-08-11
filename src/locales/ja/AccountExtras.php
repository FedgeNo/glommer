<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/AccountExtras.php for what this fragment
 * covers.
 */

return [
    'SetupForm' => [
        'siteLegend' => 'サイト',
        'siteURLLabel' => 'サイトURL',
        'siteTitleLabel' => 'サイトタイトル',
        'mailFromAddressLabel' => '送信元メールアドレス',
        'serverNameConfirmedLabel' => 'ウェブサーバーの設定で "ServerName {host}" と "UseCanonicalName On" を設定済みです(自動ライブテストが完了できない場合にのみ確認されます。詳しくはREADME.mdのHTTPSの章を参照)',
        'databaseLegend' => 'データベース',
        'databaseHostLabel' => 'データベースホスト',
        'databasePortLabel' => 'データベースポート',
        'databaseNameLabel' => 'データベース名',
        'databaseAdminUsernameLabel' => 'データベース管理者ユーザー名',
        'databaseAdminPasswordLabel' => 'データベース管理者パスワード',
        'webSocketTLSLegend' => 'WebSocket TLS(任意)',
        'certificatePathLabel' => '証明書のパス',
        'certificatePathPlaceholder' => '空欄のままにするとmkcertで自動生成されます',
        'keyPathLabel' => '鍵のパス',
        'keyPathPlaceholder' => '空欄のままにするとmkcertで自動生成されます',
        'botProtectionLegend' => 'ボット対策(任意)',
        'turnstileSiteKeyLabel' => 'Cloudflare Turnstileサイトキー',
        'turnstileSiteKeyPlaceholder' => '空欄でスキップ',
        'turnstileSecretKeyLabel' => 'Cloudflare Turnstileシークレットキー',
        'turnstileSecretKeyPlaceholder' => '空欄でスキップ',
        'submit' => 'セットアップ',
    ],

    'MessageKeyPassphraseForm' => [
        'currentPassphraseLabel' => '現在のパスフレーズ',
        'newPassphraseLabel' => '新しいパスフレーズ',
        'confirmNewPassphraseLabel' => '新しいパスフレーズ(確認)',
        'accountPasswordLabel' => 'アカウントのパスワード',
        'submit' => 'パスフレーズを変更',
    ],

    'PasswordResetForm' => [
        'legend' => '新しいパスワードを設定',
        'newPasswordLabel' => '新しいパスワード',
        'newPasswordPlaceholder' => '8文字以上',
        'confirmPasswordLabel' => '新しいパスワード(確認)',
        'submit' => 'パスワードをリセット',
    ],

    'PasswordResetRequestForm' => [
        'legend' => 'パスワードをリセット',
        'emailLabel' => 'メール',
        'submit' => 'リセットリンクを送信',
    ],

    'EmailRevertForm' => [
        'submit' => 'メールアドレスの変更を元に戻す',
    ],

    'EmailVerifyForm' => [
        'submit' => 'メールアドレスを確認',
    ],

    'EmailDigestResubscribeForm' => [
        'submit' => '再び受け取る',
    ],

    'EmailDigestSetting' => [
        'label' => 'しばらく来ていない間に見逃したことをメールで知らせる',
    ],

    'RememberedDevice' => [
        'unknownDevice' => '不明なデバイス',
        'browserOnOS' => '{os}の{browser}',
        'thisDevice' => '(このデバイス)',
        'lastUsed' => ['before' => '最終利用: ', 'after' => ''],
    ],

    'LogoutEverywherePanel' => [
        'explanation' => 'アクティブなセッションをすべて終了し、記憶されているデバイスをすべて忘れさせます。このブラウザを含め、すべてのブラウザからログアウトされます。',
    ],

    'LogoutEverywhereButton' => [
        'label' => 'すべての端末からログアウト',
    ],

    'GoogleAccountDeleteButton' => [
        'label' => 'Googleで確認して削除',
    ],

    'GoogleSignInButton' => [
        'label' => 'Googleで続ける',
    ],

    'ProfileEditButton' => [
        'ariaLabel' => 'プロフィールを編集',
    ],

    'PushNotificationSetting' => [
        'explanation' => 'サイトを開いていないときも、このデバイスに通知を届けます。ブラウザごとの設定なので、通知を受け取りたいすべてのブラウザで有効にしてください。',
        'label' => [
            'off' => 'このデバイスで有効にする',
            'on' => 'このデバイスで無効にする',
        ],
        'unsupported' => 'このブラウザはプッシュ通知に対応していません',
    ],
];
