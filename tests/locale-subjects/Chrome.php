<?php

declare(strict_types=1);

/**
 * How to build each converted piece of page furniture - merged into
 * NoEnglishInClassesTest::subjects(). See that test for what this proves.
 *
 * The two Sections are not here: building one constructs its List, and a List
 * queries the database in its own constructor. NotificationDropdown is absent
 * for the same reason - it holds a NotificationList, which loads itself.
 */

return [
    CarouselAutoplayButton::class => static fn (): HTMLObject => new CarouselAutoplayButton(),
    CarouselNextButton::class => static fn (): HTMLObject => new CarouselNextButton(),
    CarouselPrevButton::class => static fn (): HTMLObject => new CarouselPrevButton(),
    MediaFullscreenButton::class => static fn (): HTMLObject => new MediaFullscreenButton(),
    ComposerFilesRemoveButton::class => static fn (): HTMLObject => new ComposerFilesRemoveButton(),
    HelpSearch::class => static fn (): HTMLObject => new HelpSearch(),
    WelcomeBannerDismissButton::class => static fn (): HTMLObject => new WelcomeBannerDismissButton(),
    StagedPostDiscardButton::class => static fn (): HTMLObject => new StagedPostDiscardButton(),
    StagedPostPublishButton::class => static fn (): HTMLObject => new StagedPostPublishButton(),
    LogoutButton::class => static fn (): HTMLObject => new LogoutButton(),
    AvatarUploadForm::class => static fn (): HTMLObject => new AvatarUploadForm(),
    BannedUserSearchBox::class => static fn (): HTMLObject => new BannedUserSearchBox(),
];
