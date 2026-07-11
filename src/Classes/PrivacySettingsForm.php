<?php

declare(strict_types=1);

class PrivacySettingsForm extends PolicySettingsForm
{
    protected string $settingName = SitePolicy::PRIVACY_SETTING;
    protected string $legend = 'Privacy Policy';

    protected function currentText(): string
    {
        return SitePolicy::privacy();
    }
}
