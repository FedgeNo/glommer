<?php

declare(strict_types=1);

/**
 * How to build each converted post control - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * Both states of every two-state button, because a button that reads its
 * "Unlike" from the locale and still hardcodes "Like" would pass on one of them
 * and be wrong on the other.
 *
 * PostActionBar is not here: it renders the whole row, which reaches the
 * database for the viewer's like and bookmark state.
 */

return [
    PostLikeButton::class => static fn (): HTMLObject => new PostLikeButton(false, 0),
    PostPinButton::class => static fn (): HTMLObject => new PostPinButton(false),
    PostQuoteButton::class => static fn (): HTMLObject => new PostQuoteButton(1),
    PostDeleteButton::class => static fn (): HTMLObject => new PostDeleteButton(),
    PostEditButton::class => static fn (): HTMLObject => new PostEditButton(),
    ReportButton::class => static fn (): HTMLObject => new ReportButton('post', 1),
    PostEditedMarker::class => static fn (): HTMLObject => new PostEditedMarker('2026-08-11 10:00:00'),
    RepliesHeading::class => static fn (): HTMLObject => new RepliesHeading(),
    PollVoteButton::class => static fn (): HTMLObject => new PollVoteButton(1),
];
