<?php

declare(strict_types=1);

class AboutSettingsForm extends InfoSettingsForm
{
    protected string $settingName = SiteInfo::ABOUT_SETTING;
    protected string $legend = 'About';

    protected function currentText(): string
    {
        return SiteInfo::about();
    }
}
