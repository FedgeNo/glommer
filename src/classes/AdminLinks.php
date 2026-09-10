<?php

declare(strict_types=1);

/** The admin's existing detail pages, immediately below the status overview. */
class AdminLinks extends Nav
{
    public ?string $class = 'AdminLinks';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for('PageTitle');
        $this -> attributes['aria-label'] = (string) ($words['adminSettings'] ?? '');

        foreach (['/admin/reports' => 'adminReports', '/admin/banned' => 'adminBanned', '/admin/mod-settings' => 'adminModSettings', '/admin/tests' => 'adminTests'] as $path => $key) {
            $link = new Anchor($path, (string) ($words[$key] ?? ''));
            $link -> class = 'Button';
            $this -> addContent($link);
        }

        return parent::toDOM();
    }
}
