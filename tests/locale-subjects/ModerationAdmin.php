<?php

declare(strict_types=1);

/**
 * Converted moderation queue and relay admin classes - see
 * tests/NoEnglishInClassesTest.php for what this list is for.
 *
 * Several siblings in this area are ItemList subclasses that query the
 * database from their constructor (ReportList, ModerationActionList,
 * BannedTrendingEntityList, BlockedServerList, RelayList, RelayFeedList,
 * UserSearchList) or, for SiteCounters, from toDOM() itself - none can be
 * built here, where there is no database connection. HTMLLoader carries no
 * user-facing English at all, so it has no locale entry to register either.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    // Free-text fields hold marker-shaped nonsense rather than ordinary
    // English words, so a rendered fixture value can never coincidentally
    // contain a phrase the locale file also happens to use - the failure
    // that check would produce blames the class for what the fixture said.
    ReportCard::class => static fn (): HTMLObject => new ReportCard([
        'reportId' => 1,
        'reporterId' => 2,
        'reporterUsername' => 'zzreporter',
        'type' => 'post',
        'targetId' => 42,
        'reason' => 'zzreason',
        'createdAt' => '2024-01-01 00:00:00',
        'targetUserId' => 3,
        'targetUsername' => 'zztargetuser',
        'targetKind' => 'missing',
        'targetData' => 'noSnapshot',
        'targetLive' => true,
    ]),
    ModQueueLinks::class => static fn (): HTMLObject => new ModQueueLinks(),
    RelayCard::class => static fn (): HTMLObject => new RelayCard([
        'actorURI' => 'https://relay.example/actor',
        'status' => 'pending',
        'createdAt' => '2024-01-01 00:00:00',
    ]),
    RelaySubscribeForm::class => static fn (): HTMLObject => new RelaySubscribeForm(),
    RelayFollowObjectField::class => static fn (): HTMLObject => new RelayFollowObjectField(),
    TestSuitePanel::class => static fn (): HTMLObject => new TestSuitePanel(),
    HelpArticle::class => static fn (): HTMLObject => new HelpArticle('zzslug', 'zztitle', 'zzcategory', 'zzsummary', 'zzbody'),
];
