<?php

declare(strict_types=1);

/**
 * The site's Terms of Service and Privacy Policy texts - admin-editable plain
 * text stored in Settings, with shipped defaults until the admin writes their
 * own. Rendered on /terms/ and /privacy/ by PolicyText.
 */
class SitePolicy
{
    public const TERMS_SETTING = 'termsText';
    public const PRIVACY_SETTING = 'privacyText';

    private const DEFAULT_TERMS = 'You are responsible for what you post. Do not use this site to commit crimes or to post content that is illegal in the site\'s or your own jurisdiction. The site administrator may remove content or accounts at their discretion.

The site administrator has not yet customized these terms of service.';

    private const DEFAULT_PRIVACY = 'Your password is stored encrypted (hashed with a one-way algorithm) - nobody, including the site administrator, can read it.

The site administrator has not yet customized this privacy policy.';

    public static function terms(): string
    {
        $stored = (string) Settings::get(self::TERMS_SETTING, '');

        return $stored !== '' ? $stored : self::DEFAULT_TERMS;
    }

    public static function privacy(): string
    {
        $stored = (string) Settings::get(self::PRIVACY_SETTING, '');

        return $stored !== '' ? $stored : self::DEFAULT_PRIVACY;
    }
}
