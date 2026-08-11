<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/AccountForms.php for what this fragment
 * covers.
 */

return [
    'LoginForm' => [
        'legend' => 'ログイン',
        'identifier' => 'ユーザー名またはメール',
        'password' => 'パスワード',
        'rememberMe' => 'ログイン状態を保持',
        'submit' => 'ログイン',
    ],

    'SignupForm' => [
        'legend' => 'アカウントを作成',
        'usernameLabel' => 'ユーザー名',
        'usernamePlaceholder' => '半角英小文字・数字・_',
        'emailLabel' => 'メール',
        'emailPlaceholder' => '有効なメールアドレス',
        'displayName' => '表示名(任意)',
        'bioLabel' => '自己紹介(任意)',
        'bioPlaceholder' => '簡単な自己紹介 - #ハッシュタグ、@メンション、リンクはリンクとして表示されます',
        'passwordLabel' => 'パスワード',
        'passwordPlaceholder' => 'パスワード: 8文字以上',
        'rememberMe' => 'ログイン状態を保持',
        'submit' => '新規登録',
    ],

    'PasswordChangeForm' => [
        'legend' => 'パスワードを変更',
        'currentPassword' => '現在のパスワード',
        'newPasswordLabel' => '新しいパスワード',
        'newPasswordPlaceholder' => '8文字以上',
        'confirmPassword' => '新しいパスワード(確認)',
        'submit' => 'パスワードを変更',
    ],

    'EmailChangeForm' => [
        'legend' => 'メールアドレスを変更',
        'newEmail' => '新しいメールアドレス',
        'currentPassword' => '現在のパスワード',
        'notice' => 'サイトの利用を続けるには、新しいアドレスの確認が必要です。',
        'submit' => 'メールアドレスを変更',
    ],

    'AccountDeleteForm' => [
        'legend' => 'アカウントを削除',
        'warning' => 'アカウント、投稿、メッセージが完全に削除されます。この操作は取り消せません。',
        'currentPassword' => '現在のパスワード',
        'submit' => 'アカウントを削除',
    ],

    'AccountMigrationForm' => [
        'legend' => '別のサーバーへ移行',
        'movedNotice' => 'このアカウントは{destination}に移行しました。フォロワーには移行先をフォローするよう案内されました。',
        'explanation' => 'フォロワーには新しいアカウントをフォローするよう案内されます。投稿はこのサーバーに残ります - オブジェクトのアドレスはそれを作ったサーバーに属するため、持ち出すことはできません。',
        'addressNotice' => '移行先のアカウントで、先にこのアカウントを「別名(also known as)」として登録しておく必要があります。このアカウントのアドレスは{address}です。',
        'movedToLabel' => '移行先',
        'movedToPlaceholder' => 'https://example.social/users/you',
        'aliasesLegend' => '別名(Also Known As)',
        'aliasesExplanation' => '他のサーバーにある、自分自身のアカウント。ここに登録すると、そのアカウントからこのアカウントへの移行が許可されます - あくまで許可であり、移行そのものではありません。1行に1アドレス。',
        'aliasesLabel' => '自分の他のアカウント',
        'aliasesPlaceholder' => 'https://example.social/users/you',
        'submit' => '保存',
    ],

    'TwoFactorForm' => [
        'legend' => '確認コードを入力',
        'explanation' => '確認コードをメールで送信しました。ログインを完了するには、下に入力してください。',
        'code' => '確認コード',
        'submit' => '確認',
    ],

    'TwoFactorSettingsForm' => [
        'legend' => ['on' => '二段階認証は有効です', 'off' => '二段階認証は無効です'],
        'explanation' => [
            'on' => 'ログイン時、入力が必要な確認コードをメールでお送りします。',
            'off' => 'ログインにもう一段階追加します: 入力が必要な確認コードをメールでお送りするので、パスワードだけではログインできなくなります。',
        ],
        'currentPassword' => '現在のパスワード',
        'submit' => ['on' => '二段階認証を無効にする', 'off' => '二段階認証を有効にする'],
    ],

    'VerificationNotice' => [
        'instructions' => '受信トレイを確認し、メールアドレスを確認するために送信した確認リンクをクリックしてください。見当たらない場合は、迷惑メールフォルダもご確認ください。',
    ],

    'VerificationResendButton' => [
        'label' => '確認メールを再送信',
    ],
];
