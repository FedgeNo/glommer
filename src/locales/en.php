<?php

declare(strict_types=1);

/**
 * The words the software says, in English.
 *
 * This file is the source every other locale is measured against: a key
 * missing from another locale falls back to what is written here, so a
 * half-translated site is a working site and one class can be converted per
 * commit rather than all thousand strings at once.
 *
 * Keyed by the class that says them. Every concept here is already an
 * HTMLObject subclass whose name is the PHP class, the CSS selector and the JS
 * selector at once, so there is no key to invent and none that can drift from
 * the thing it names.
 *
 * A sentence is a list of named pieces rather than one string, because a link
 * or a button sits inside it and its label is a translatable string too. Which
 * piece carries the words is what moves the control: a language that ends on
 * the control fills `before` and leaves `after` empty. Named rather than
 * numbered so a sentence can gain a piece without renumbering every locale.
 */

return [
    'LoginPrompt' => [
        // One phrasing per thing the reader was trying to do. A language need
        // not say "to reply" as a suffix, or at all, so the whole sentence is
        // written per action rather than assembled from a stem and a verb.
        'reply' => ['before' => '', 'link' => 'Log in', 'after' => ' to reply.'],
        'post' => ['before' => '', 'link' => 'Log in', 'after' => ' to post.'],
    ],
];
