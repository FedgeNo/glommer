<?php

declare(strict_types=1);

/**
 * How to build one of each converted post/social class - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * ThreadStartLink is not here: it is a bare Anchor subclass with no toDOM of
 * its own and no words - its label ('Jump to start') is ThreadContext's to
 * say, passed in at construction, the same way LoginPrompt hands a plain
 * Anchor its label rather than an Anchor subclass owning one.
 */
return [
    MoreLocationsLink::class => static fn (): HTMLObject => new MoreLocationsLink(),

    // The place-search box - and its two strings - only builds when
    // Auth::check() is true, which it never is under this runner; only the
    // heading, description and two buttons get automated coverage here.
    NearbyLocationPrompt::class => static fn (): HTMLObject => new NearbyLocationPrompt(),

    PollDeadline::class => static fn (): HTMLObject => new PollDeadline(gmdate('Y-m-d H:i:s', time() + (3 * 86400)), false),
    PollTally::class => static fn (): HTMLObject => new PollTally(3, gmdate('Y-m-d H:i:s', time() + (3 * 86400)), false),

    // Auth::check() is false under this runner, so both composers render
    // their signed-out prompt - the only branch either one has words for.
    PostComposer::class => static fn (): HTMLObject => new PostComposer(),
    ReplyComposer::class => static fn (): HTMLObject => new ReplyComposer(1),

    RepostAttribution::class => static fn (): HTMLObject => new RepostAttribution('alice', 'Alice'),

    // parentLabel: null exercises the "untitled" fallback along with the
    // sentence around it; a rootId different from parentId exercises
    // ThreadStartLink's "Jump to start" in the same render.
    ThreadContext::class => static fn (): HTMLObject => new ThreadContext([
        'parentId' => 1,
        'parentUsername' => 'alice',
        'parentLabel' => null,
        'rootId' => 2,
        'rootUsername' => 'bob',
    ]),

    TopicSummaryCard::class => static fn (): HTMLObject => new TopicSummaryCard('A short summary of the topic.', 'hashtag', 'example'),

    WelcomeBanner::class => static fn (): HTMLObject => new WelcomeBanner(),
];
