<?php

declare(strict_types=1);

class TermsSettingsForm extends PolicySettingsForm
{
    protected string $settingName = SitePolicy::TERMS_SETTING;
    protected string $legend = 'Terms of Service';

    protected function currentText(): string
    {
        return SitePolicy::terms();
    }
}
