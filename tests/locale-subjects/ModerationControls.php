<?php

declare(strict_types=1);

/**
 * How to build each converted moderation control - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * BannedTrendingEntity and BlockedServerCard are not here: both are hydrated
 * straight off a query and render a RelativeTime out of a column, so neither
 * can be built without a database.
 */

return [
    UserModButton::class => static fn (): HTMLObject => new UserModButton(2, false),
    UserUnbanButton::class => static fn (): HTMLObject => new UserUnbanButton(2),
    TrendingEntityBanButton::class => static fn (): HTMLObject => new TrendingEntityBanButton('hashtag', 'example'),
    TrendingEntityUnbanButton::class => static fn (): HTMLObject => new TrendingEntityUnbanButton('hashtag', 'example'),
    ReportDismissButton::class => static fn (): HTMLObject => new ReportDismissButton(1),
    ReportedContentClassifyButton::class => static fn (): HTMLObject => new ReportedContentClassifyButton(1),
    ServerUnblockButton::class => static fn (): HTMLObject => new ServerUnblockButton('example.social'),
    RelayUnsubscribeButton::class => static fn (): HTMLObject => new RelayUnsubscribeButton('https://relay.example/actor'),
    RememberedDeviceRevokeButton::class => static fn (): HTMLObject => new RememberedDeviceRevokeButton(1),
    TestResultsBadge::class => static fn (): HTMLObject => new TestResultsBadge('PHP', true),
];
