<?php

declare(strict_types=1);

class AboutSettingsForm extends InfoSettingsForm
{
    public ?string $description = 'Plain text - blank lines separate paragraphs. First paragraph will be used as site description.';
    protected string $settingName = SiteInfo::ABOUT_SETTING;
    protected string $legend = 'About';

    protected function currentText(): string
    {
        return SiteInfo::about();
    }
}
