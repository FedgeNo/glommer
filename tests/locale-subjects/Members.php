<?php

declare(strict_types=1);

/**
 * How to build each converted member-facing class - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * User is not here: its render reads friendship state and post counts out of
 * the database, so it cannot be built without one. Its one string is held to
 * the same standard by UserProfileTest, which has a database and does the
 * marker check by hand.
 */

return [
    ThemeSelector::class => static fn (): HTMLObject => new ThemeSelector(),
    FriendRequestButton::class => static fn (): HTMLObject => new FriendRequestButton(2, false),
    StagedPostWhen::class => static fn (): HTMLObject => new StagedPostWhen('2026-08-12 09:00:00'),
];
