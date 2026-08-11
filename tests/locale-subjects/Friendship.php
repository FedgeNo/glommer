<?php

declare(strict_types=1);

/**
 * How to build each converted friendship control - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * Both states of the two-state buttons, since a class can read one wording
 * from the locale and still hardcode the other.
 *
 * OtherUser is not here: it renders a whole profile card off the database.
 */

return [
    FriendRemoveButton::class => static fn (): HTMLObject => new FriendRemoveButton(2),
    FriendRequestAcceptButton::class => static fn (): HTMLObject => new FriendRequestAcceptButton(2),
    FriendRequestDenyButton::class => static fn (): HTMLObject => new FriendRequestDenyButton(2),
    UserFollowButton::class => static fn (): HTMLObject => new UserFollowButton(2, false),
    UserBlockButton::class => static fn (): HTMLObject => new UserBlockButton(2),
    UserUnblockButton::class => static fn (): HTMLObject => new UserUnblockButton(2),
];
