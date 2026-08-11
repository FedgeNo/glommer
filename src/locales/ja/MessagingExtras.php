<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/MessagingExtras.php for what this fragment
 * covers.
 */

return [
    'Notification' => [
        'postReady' => 'メディアの処理が完了し、公開されました',
        'scheduledPostLive' => '予約投稿が公開されました',
        'uploadPartlyFailed' => '投稿は公開されましたが、一部のファイルを処理できませんでした',
        'uploadFailed' => 'アップロードの処理に失敗し、投稿されませんでした',
        'mailerFailed' => 'メールの送信に失敗しました - メール送信の仕組みが停止している可能性があります。メールの設定を確認してください。',
        'mailFromNotConfigured' => '送信元メールアドレスが設定されていないため、メールを送信できません。管理者設定(送信メールの項目)またはbin/install.phpから設定してください。',
        'systemError' => 'サーバーエラーが発生しました。詳細はエラーログを確認してください。',
        'passwordRemovedGoogle' => 'Googleでサインインしたため、パスワードは削除されました。新しく設定するには「パスワードを忘れた場合」を使用してください。',
        'like' => '{name}さんがあなたの投稿にいいねしました',
        'repost' => '{name}さんがあなたの投稿をリポストしました',
        'reply' => '{name}さんがあなたの投稿に返信しました',
        'friendRequest' => '{name}さんから友達申請が届きました',
        'friendAccepted' => '{name}さんが友達申請を承認しました',
        'message' => '{name}さんからメッセージが届きました',
        'mention' => '{name}さんが投稿であなたをメンションしました',
        'follow' => '{name}さんが他のサーバーからあなたをフォローしました',
        'default' => '{name}さんが何かをしました',
    ],

    'NotificationList' => [
        'emptyNotice' => 'まだ通知はありません。',
    ],

    'NotificationsNavLink' => [
        'label' => '通知',
        'unseen' => '未読の通知',
    ],

    'NotificationTestPanel' => [
        'intro' => '自分自身(管理者)宛てにテスト通知を送信します。トーストと通知ドロップダウンの両方に即座に表示されるはずです。',
        'button' => 'テスト通知を送信',
        'sending' => '送信中…',
        'sent' => '送信しました!',
        'failed' => '失敗しました',
    ],

    'MessageDot' => [
        'label' => '未読メッセージ',
    ],

    'NavAlertDot' => [
        'label' => 'メニューに新着があります',
    ],

    'Message' => [
        'encrypted' => '暗号化されたメッセージ',
        'decryptionFailed' => 'このメッセージは、既に存在しない鍵で暗号化されています。',
    ],

    'MessageComposer' => [
        'bodyLabel' => 'メッセージ',
        'bodyPlaceholder' => 'メッセージを入力',
        'send' => '送信',
    ],

    'MessageList' => [
        'emptyNotice' => 'まだメッセージはありません。',
    ],

    'MessageKeyFingerprint' => [
        'explanation' => 'このコードは、声に出す、直接会う、通話するなど、別の方法でお互いに読み合わせてください。両者で一致すれば、間に誰も入っていないことになります。',
        'changed' => '前回確認したときからコードが変わっています。これは相手が暗号鍵をリセットした場合に起こりますが、この会話が読まれている場合も同じように見えます。信用する前に、新しいコードを相手と確認してください。',
        'verified' => 'このコードは確認済みです。',
    ],

    'MessageKeyVerifyButton' => [
        'label' => '確認済みにする',
    ],

    'MessageUnlockForm' => [
        'passphraseLabel' => 'パスフレーズ',
        'passphrasePlaceholder' => 'この会話を開くパスフレーズ',
        'submit' => '開く',
    ],

    'Conversation' => [
        'lastMessage' => ['before' => '最終メッセージ: ', 'after' => ''],
    ],

    'SensitiveMedia' => [
        'summary' => '閲覧注意のメディア',
    ],

    'SensitiveMediaSetting' => [
        'toggle' => '閲覧注意のメディアを常に表示する',
    ],
];
