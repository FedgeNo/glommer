<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/ModerationAdmin.php for what this fragment
 * covers.
 */

return [
    'ReportCard' => [
        'targetTypes' => [
            'post' => '投稿',
            'message' => 'メッセージ',
            'user' => 'ユーザー',
        ],
        'summary' => ['before' => '{type} #{id} の通報者: ', 'after' => ''],
        'reasonLine' => '理由: {reason}',
        'banReporterLabel' => '通報者をBAN',
        'banReportedUserLabel' => '通報されたユーザーをBAN',
        'deleteLabel' => '{type}を削除',
        'reportedImageAlt' => '通報された画像',
        'attachmentUnavailable' => '通報された添付ファイルは利用できません。',
        'viewAttachment' => '通報された添付ファイルを見る',
        'missing' => [
            'noSnapshot' => '通報された内容は利用できません。',
            'unknownType' => '不明なコンテンツタイプです。',
        ],
    ],

    'ReportList' => [
        'emptyNotice' => '通報はありません。',
    ],

    'ModQueueLinks' => [
        'intro' => 'キューは1ページずつ読むのにちょうどよい長さがあるため、それぞれ専用のページがあります。',
        'reportsLabel' => '通報',
        'bannedUsersLabel' => 'BANされたユーザー',
    ],

    'ModerationActionList' => [
        'emptyNotice' => 'モデレーターによる操作はまだありません。',
    ],

    'BannedTrendingEntityList' => [
        'emptyNotice' => 'BANされたトレンドはありません。',
    ],

    'BlockedServerList' => [
        'emptyNotice' => 'ブロックされたサーバーはありません。',
    ],

    'RelayCard' => [
        'accepted' => '登録: ',
        'waiting' => 'リレーの承認待ち・登録: ',
    ],

    'RelayList' => [
        'emptyNotice' => 'リレーに登録していません。メンバーがフォローしたもの以外は、ここには何も届きません。',
    ],

    'RelayFeedList' => [
        'emptyNotice' => 'リレー経由の投稿はまだありません。相手側のサーバーが投稿を公開すると、ここに表示されます。',
    ],

    'RelaySubscribeForm' => [
        'legend' => 'リレーに登録',
        'explainerOne' => 'リレーは共有のファイアホースです: 登録している他のすべてのサーバーの公開投稿がここに届き、このサーバーの投稿もそれらすべてに送られます。連合は本来ここで誰かが既にフォローしているものしか運ばないため、新しいインスタンスが誰かを見つける手段はこれが頼りです。',
        'explainerTwo' => '負荷は予測できるものではありません - ある週は静かでも、次の週には1時間に数千件の投稿が来ることもあり、ストレージ、配送キュー、モデレーションキューのすべてがその影響を受けます。リレー経由の投稿はメインフィードや友達フィードには表示されず、あえて開かれるリレーフィードに表示されます。',
        'addressLabel' => 'リレーのアドレス',
        'addressPlaceholder' => 'https://relay.example/actor',
        'submitLabel' => '登録',
    ],

    'RelayFollowObjectField' => [
        'label' => '登録方式',
        'options' => [
            'public' => '公開ストリームをフォロー(ほとんどのリレーが想定する方式)',
            'actor' => 'リレー自身のアクターをフォロー',
        ],
        'retryNotice' => 'リレーがいつまでも承認しない場合は、一度取り下げてもう一方の方式を試してください - リレー側のソフトウェアによっては、どちらか一方にしか対応していないことがあります。',
    ],

    'SiteCounters' => [
        'members' => 'メンバー: {count}人(直近{days}日間で{joined}人が参加)',
        'activeMembers' => '直近{days}日間にアクティブだったメンバー: {count}人(うち{posted}人が投稿)',
        'posts' => 'このサイトで書かれた投稿: {count}件(直近{days}日間で{recent}件)',
        'deliveries' => '直近{days}日間の連合配送: {delivered}件成功、{undeliverable}件断念',
        'queued' => '配送待ち: {count}件(うち{failing}件は一度以上失敗済み)',
        'pendingReads' => '他のサーバーから読み込み待ちの投稿: {count}件',
    ],

    'TestSuitePanel' => [
        'intro' => 'サイトのテストスイートを実行し、結果を表示します。数秒かかるため、専用のページで開きます。',
        'runLabel' => 'テストを実行',
    ],

    'HelpArticle' => [
        'backLabel' => 'ヘルプ一覧に戻る',
    ],

    'UserSearchList' => [
        'noSuggestions' => '現在、おすすめはありません - おすすめは、すでに友達になっている人の友達から選ばれます。名前で探すには上の検索を使ってください。',
        'noMatches' => '該当するユーザーが見つかりません。',
    ],
];
