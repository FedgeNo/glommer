<?php

declare(strict_types=1);

/**
 * How to build the navigation - merged into NoEnglishInClassesTest::subjects().
 * See that test for what this proves.
 *
 * Signed out, because that is the branch that renders without a session: the
 * dots both return false for a null user before they query, and the relay check
 * is only reached once somebody is logged in. That branch reaches five of the
 * link labels and both account ones, which is enough for the test to catch this
 * class putting words back - the signed-in labels live in the same entry and
 * are read the same way.
 */

return [
    MainNavigation::class => static fn (): HTMLObject => new MainNavigation(),
    LogoutForm::class => static fn (): HTMLObject => new LogoutForm(),
];
