<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/MessageTranslation.php for what this fragment
 * covers.
 */

return [
    'MessageTranslateButton' => [
        'name' => 'このメッセージを翻訳',
    ],

    'MessageTranslationNotice' => [
        'heading' => 'メッセージの翻訳について',
        'body' => 'メッセージを翻訳すると、その本文がこのサーバーに送信され、ここで翻訳されます。何も保存されません: 翻訳結果はデータベースに書き込まれず、メッセージ自体も変更されません。ただし、この方法で翻訳されたメッセージはサーバーに読み取られたことになるため、翻訳していないメッセージのようなエンドツーエンド暗号化ではなくなります。翻訳できるのは受信したメッセージのみで、自分が求めたときだけです。',
        'confirm' => '翻訳する',
        'cancel' => '今はしない',
    ],
];
