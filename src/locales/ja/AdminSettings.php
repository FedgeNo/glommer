<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/AdminSettings.php for what this fragment
 * covers.
 */

return [
    'MailSettingsForm' => [
        'legend' => '送信メール',
        'fromAddressLabel' => '送信元アドレス',
        'fromAddressPlaceholder' => 'これを設定するまでメールは送信できません',
        'fromNameLabel' => '送信者名',
        'hostLabel' => 'SMTPホスト',
        'portLabel' => 'SMTPポート',
        'usernameLabel' => 'SMTPユーザー名',
        'passwordLabel' => 'SMTPパスワード',
        'passwordPlaceholder' => [
            'set' => '設定済み - 空欄のままにすると変更されません',
            'unset' => 'SMTPパスワード',
        ],
        'encryptionLabel' => '暗号化',
        'encryptionOptions' => [
            'tls' => 'TLS(STARTTLS、通常はポート587)',
            'ssl' => 'SSL(暗黙的TLS、通常はポート465)',
            'none' => 'なし',
        ],
        'explainer' => 'SMTPホストを空欄にすると、代わりにPHPのmail()で送信します(非推奨 - 詳しくはREADMEの配信性の章を参照)。「送信元」アドレスを設定するまで、メールは一切送信できません。',
        'save' => '保存',
    ],

    'MapSettingsForm' => [
        'legend' => '地図タイル',
        'notice' => '空欄にするとOpenStreetMapを使用します。APIキーが必要なプロバイダーを使う場合は、キーを入れる位置に文字どおり{apiKey}と書いたURLテンプレートを貼り付け、下にキーとクレジット表記を入力してください。',
        'urlLabel' => 'タイルURLテンプレート',
        'keyLabel' => 'APIキー',
        'keyPlaceholder' => 'タイルプロバイダーのAPIキー',
        'attributionLabel' => 'クレジット表記',
        'attributionPlaceholder' => '© OpenStreetMap 貢献者',
        'save' => '保存',
    ],

    'GoogleAuthSettingsForm' => [
        'legend' => 'Googleサインイン',
        'clientIdLabel' => 'クライアントID',
        'clientIdPlaceholder' => 'Google OAuthクライアントID',
        'secretLabel' => 'クライアントシークレット',
        'secretPlaceholder' => [
            'set' => '設定済み - 空欄のままにすると変更されません',
            'unset' => 'Google OAuthクライアントシークレット',
        ],
        'explainer' => '両方を設定すると、サインアップとログインの画面に「Googleで続ける」が表示されます。Google CloudのOAuthクライアントで、承認済みのリダイレクトURIを{url}に設定してください - クライアントIDを空にすると無効になります。',
        'save' => '保存',
    ],

    'BotProtectionSettingsForm' => [
        'turnstileLegend' => 'Cloudflare Turnstile',
        'turnstileSiteKeyLabel' => 'サイトキー',
        'turnstileSiteKeyPlaceholder' => 'Cloudflare Turnstileのサイトキー',
        'turnstileSecretKeyLabel' => 'シークレットキー',
        'turnstileSecretKeyPlaceholder' => [
            'set' => '設定済み - 空欄のままにすると変更されません',
            'unset' => 'Cloudflare Turnstileのシークレットキー',
        ],
        'turnstileExplainer' => 'サインアップとログインにCAPTCHAを表示するには、両方のキーが必要です。サイトキーを空にすると無効になります。',
        'recaptchaLegend' => 'Google reCAPTCHA(アカウントロック復旧用)',
        'recaptchaSiteKeyLabel' => 'サイトキー',
        'recaptchaSiteKeyPlaceholder' => 'Google reCAPTCHA v2のサイトキー',
        'recaptchaSecretKeyLabel' => 'シークレットキー',
        'recaptchaSecretKeyPlaceholder' => [
            'set' => '設定済み - 空欄のままにすると変更されません',
            'unset' => 'Google reCAPTCHA v2のシークレットキー',
        ],
        'recaptchaExplainer' => '両方のキーが必要です。設定すると、ログイン試行回数の上限に達したアカウントは、ロック解除を待つ代わりにこの認証を通過することで再びログインできます。未設定の場合、ロックの解除は待つ以外に方法がありません。reCAPTCHA v2(「私はロボットではありません」)を使用してください。サイトキーを空にすると無効になります。',
        'save' => '保存',
    ],

    'OpenRouterSettingsForm' => [
        'legend' => 'OpenRouter',
        'notice' => 'サイト内のAI機能(トレンドトピックの要約など)で使用します。モデルを空欄にすると無料モデルルーターが使われ、OpenRouterがそのとき無料なモデルの中からランダムに選ぶため、料金が発生することはありません。',
        'keyLabel' => 'APIキー',
        'keyPlaceholder' => [
            'set' => '設定済み - 空欄のままにすると変更されません',
            'unset' => 'OpenRouterのAPIキー',
        ],
        'clearKeyLabel' => '保存されているAPIキーを削除する(AI機能を無効にします)',
        'modelLabel' => 'モデル',
        'neverSpendLabel' => '料金が発生する操作を許可しない(推奨)',
        'explainer' => 'このガードを有効にすると、すべてのリクエストの上限価格が0円になるため、無料のプロバイダーが使えないときは有料モデルにフォールバックせず、そのまま失敗します。AI機能を完全に無効にするには、保存されているAPIキーを削除してください。',
        'save' => '保存',
    ],

    'AboutSettingsForm' => [
        'legend' => 'このサイトについて',
        'description' => 'プレーンテキスト - 空行で段落を区切ります。最初の段落がサイトの説明として使われます。',
        'save' => '保存',
    ],

    'TermsSettingsForm' => [
        'legend' => '利用規約',
        'description' => 'プレーンテキスト - 空行で段落を区切ります。',
        'save' => '保存',
    ],

    'PrivacySettingsForm' => [
        'legend' => 'プライバシーポリシー',
        'description' => 'プレーンテキスト - 空行で段落を区切ります。',
        'save' => '保存',
    ],

    'EmailDigestSettingsForm' => [
        'legend' => 'メールダイジェスト',
        'fieldLabel' => '結びの段落',
        'notice' => '各ダイジェストの末尾、見逃した内容の一覧の後に追加されます。プレーンテキストです。空欄にすると、このソフトウェアに付属する既定の文言に戻ります。',
        'save' => '保存',
    ],

    'FaviconSettingsForm' => [
        'legend' => 'ファビコン',
        'currentAlt' => '現在のファビコン',
        'save' => 'ファビコンをアップロード',
    ],

    'FrontPageImageSettingsForm' => [
        'legend' => 'フロントページ画像',
        'explainer' => '他のサイトでこのサイトのリンクが共有されたときに表示されます - Open Graphのメタデータとしてのみ使われ、ページ自体には表示されません。1200×630にトリミングされます。未設定の場合、リンクのプレビューに画像は表示されません。',
        'currentAlt' => '現在のフロントページ画像',
        'save' => '画像をアップロード',
    ],
];
