<?php

declare(strict_types=1);

/**
 * Japanese. See src/locales/en/EntityType.php for what this fragment covers.
 *
 * Japanese nouns do not inflect for number, so the 'singular' and 'plural'
 * lists below carry the same word under each key - both are still written
 * out, because this split is not a counted entry (see the English file's own
 * docblock) and so does not collapse the way PostActionBar.replies or
 * RelativeTime.minutes do.
 */

return [
    'EntityType' => [
        'singular' => [
            'hashtag' => 'ハッシュタグ',
            'person' => '人物',
            'org' => '組織',
            'gpe' => '場所',
            'loc' => '地域',
            'fac' => 'ランドマーク',
            'product' => '製品',
            'event' => 'イベント',
            'work_of_art' => '作品',
            'law' => '法律',
            'language' => '言語',
            'norp' => 'グループ',
        ],
        'plural' => [
            'hashtag' => 'ハッシュタグ',
            'person' => '人物',
            'org' => '組織',
            'gpe' => '場所',
            'loc' => '地域',
            'fac' => 'ランドマーク',
            'product' => '製品',
            'event' => 'イベント',
            'work_of_art' => '作品',
            'law' => '法律',
            'language' => '言語',
            'norp' => 'グループ',
        ],
    ],
];
