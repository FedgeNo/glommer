<?php

declare(strict_types=1);

/**
 * Converted admin Site Settings forms - see tests/NoEnglishInClassesTest.php
 * for what this list is for.
 *
 * @return array<string, callable(): HTMLObject>
 */
return [
    MailSettingsForm::class => static fn (): HTMLObject => new MailSettingsForm(),
    MapSettingsForm::class => static fn (): HTMLObject => new MapSettingsForm(),
    GoogleAuthSettingsForm::class => static fn (): HTMLObject => new GoogleAuthSettingsForm(),
    BotProtectionSettingsForm::class => static fn (): HTMLObject => new BotProtectionSettingsForm(),
    OpenRouterSettingsForm::class => static fn (): HTMLObject => new OpenRouterSettingsForm(),
    // SiteInfoSettingsForm itself is abstract - only its descendants below are
    // constructible, and each carries its own locale entry via static::class.
    AboutSettingsForm::class => static fn (): HTMLObject => new AboutSettingsForm(),
    TermsSettingsForm::class => static fn (): HTMLObject => new TermsSettingsForm(),
    PrivacySettingsForm::class => static fn (): HTMLObject => new PrivacySettingsForm(),
    EmailDigestSettingsForm::class => static fn (): HTMLObject => new EmailDigestSettingsForm(),
    FaviconSettingsForm::class => static fn (): HTMLObject => new FaviconSettingsForm(),
    FrontPageImageSettingsForm::class => static fn (): HTMLObject => new FrontPageImageSettingsForm(),
];
