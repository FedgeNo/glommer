<?php

declare(strict_types=1);

class TermsSettingsForm extends InfoSettingsForm
{
    protected string $settingName = SiteInfo::TERMS_SETTING;
    protected string $legend = 'Terms of Service';

    protected function currentText(): string
    {
        return SiteInfo::terms();
    }
}
