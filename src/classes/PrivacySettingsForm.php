<?php

declare(strict_types=1);

class PrivacySettingsForm extends SiteInfoSettingsForm
{
    protected string $settingName = SiteInfo::PRIVACY_SETTING;
    protected string $legend = 'Privacy Policy';

    protected function currentText(): string
    {
        return SiteInfo::privacy();
    }
}
