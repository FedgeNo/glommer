<?php

declare(strict_types=1);

/**
 * Japanese. Anything not written here falls back to src/locales/en.php.
 *
 * Japanese does not inflect a noun or a verb for number, so there is exactly
 * one phrasing regardless of the count - the rule below returns 'other' for
 * every count, and every counted entry throughout src/locales/ja/ carries
 * only that form. See Strings::PLURAL_RULE's own docblock, which names
 * Japanese as exactly this case.
 */

return [
    Strings::PLURAL_RULE => static fn (int $count): string => 'other',

    'PollOptionVotes' => [
        'votes' => ['other' => '{count}票'],
    ],

    'LoginPrompt' => [
        'reply' => ['before' => '返信するには', 'link' => 'ログイン', 'after' => 'してください。'],
        'post' => ['before' => '投稿するには', 'link' => 'ログイン', 'after' => 'してください。'],
    ],
];
