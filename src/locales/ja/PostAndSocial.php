<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/PostAndSocial.php for what this fragment
 * covers.
 */

return [
    'MoreLocationsLink' => [
        'moreLocations' => ['before' => '', 'link' => '他の地域', 'after' => 'を見る'],
    ],

    'NearbyLocationPrompt' => [
        'heading' => 'あなたの近くの投稿',
        'description' => 'ある地点に最も近い投稿を表示します - 活動がある場所ならどこでも、どれだけ離れていても構いません。現在地を共有すればそこから、または地図上で地点を選んでもかまいません。',
        'useMyLocation' => '現在地を使う',
        'pickOnMap' => '地図で選ぶ',
        'searchPlaceholder' => 'または地名を入力…',
        'searchLabel' => '場所を検索',
        'locating' => '取得中…',
        'noGeolocation' => 'このブラウザは位置情報を共有できません。',
        'locationError' => '位置情報を取得できませんでした。ブラウザの位置情報の許可設定を確認してください。',
    ],

    'PollDeadline' => [
        'final' => '最終結果',
        'closes' => ['before' => '', 'after' => 'で終了'],
        'days' => ['other' => '{count}日'],
        'hours' => ['other' => '{count}時間'],
        'minutes' => ['other' => '{count}分'],
        'underMinute' => '1分未満',
    ],

    'PollTally' => [
        'voters' => ['other' => '{count}人が投票・'],
    ],

    'PostComposer' => [
        'prompt' => ['before' => '投稿するには', 'link' => 'ログイン', 'after' => 'してください。'],
    ],

    'ReplyComposer' => [
        'prompt' => ['before' => '返信するには', 'link' => 'ログイン', 'after' => 'してください。'],
    ],

    'RepostAttribution' => [
        'attribution' => ['before' => '', 'after' => 'がリポスト'],
    ],

    'ThreadContext' => [
        'response' => ['before' => '返信先: ', 'after' => ''],
        'untitled' => 'この投稿',
        'jumpToStart' => '最初の投稿へ',
    ],

    'TopicSummaryCard' => [
        'label' => 'AI生成の要約',
    ],

    'WelcomeBanner' => [
        'heading' => ['before' => '', 'after' => 'へようこそ'],
        'paragraphs' => [
            '下のボックスに何か書けば、あなたのフィードに投稿されます。誰でも返信でき、返信は親を持つ投稿にすぎないので、会話は必要なだけ深く入れ子になります。',
            '人を友達に追加すると、その人の投稿がフィードに加わります。左上のサイト名からグローバルフィードを開くと、ここで書かれたすべてが表示されるので、追加する相手を見つけるにはそこを見てください。',
            'このサイトはフェディバースの一部です: Mastodonなどほかのサーバーのアカウントをフルハンドルでフォローでき、あなたが投稿した内容は、あちらであなたをフォローしている人にも届きます。@someone@example.socialのようなハンドルを検索すると、このサーバーが探しに行きます。',
            '投稿に#ハッシュタグを付けると、そのタグのページに表示され、十分な人数が話題にしていればトレンドにも表示されます。',
            'メンバー間のメッセージはエンドツーエンドで暗号化されます - サーバーが保存するのは読み取れない暗号文だけです。設定でこれを有効にしてください。',
            'すぐに投稿する必要はありません: 下書きを保存するか、時間を設定すれば自動で公開されます。どちらも「下書き・予約投稿」にあります。',
        ],
        'more' => ['before' => '詳しくは', 'link' => 'ヘルプページ', 'after' => 'をご覧ください。別のサーバーからアカウントを移す方法なども載っています。'],
        'dontShowAgain' => '今後表示しない',
    ],
];
