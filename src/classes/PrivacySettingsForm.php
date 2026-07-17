<?php

declare(strict_types=1);

class PrivacySettingsForm extends InfoSettingsForm
{
    protected string $settingName = SiteInfo::PRIVACY_SETTING;
    protected string $legend = 'Privacy Policy';

    protected function currentText(): string
    {
        return SiteInfo::privacy();
    }
}
