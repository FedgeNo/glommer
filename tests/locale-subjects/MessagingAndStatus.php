<?php

declare(strict_types=1);

/**
 * How to build one of each converted messaging/status class - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * EncryptedMessagesSetting is not here: its toDOM() reads
 * Auth::user() -> messagePublicKey, and Auth::user() is null outside a real
 * session, so it cannot be built from a plain test without a database.
 */
return [
    MessageKeySetupForm::class => static fn (): HTMLObject => new MessageKeySetupForm(true),
    MessagePrivacyButton::class => static fn (): HTMLObject => new MessagePrivacyButton('encrypted', 'example@example.social'),
    RemoteFollowsForm::class => static fn (): HTMLObject => new RemoteFollowsForm([
        ['displayName' => 'Example Person', 'slug' => 'example-person', 'status' => 'pending'],
    ]),
    ServerBlockForm::class => static fn (): HTMLObject => new ServerBlockForm(),
    VideoCallTestPanel::class => static fn (): HTMLObject => new VideoCallTestPanel(),
    VideoCallTestButton::class => static fn (): HTMLObject => new VideoCallTestButton(),
    WebSocketStatus::class => static fn (): HTMLObject => new WebSocketStatus(),
    UploadWorkerStatus::class => static fn (): HTMLObject => new UploadWorkerStatus(),
    TrendingTimerStatus::class => static fn (): HTMLObject => new TrendingTimerStatus(),
];
