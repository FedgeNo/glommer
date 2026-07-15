<?php

declare(strict_types=1);

/**
 * Read-only trending-timer health for the admin Site Settings page - whether
 * glommer-trending.timer is confirmed armed and waiting for its next run.
 * Mirrors UploadWorkerStatus's three-way active/not-running/unknown states
 * and the same SELinux status-query caveat.
 */
class TrendingTimerStatus extends Div
{
    public ?string $class = 'Card d-flex flex-column gap-2 TrendingTimerStatus';

    public function toDOM(): \DOMElement
    {
        $is_active = Trending::timerIsActive();

        $status_text = match ($is_active) {
            true => 'Running',
            false => 'Not running - trending topics will only refresh via the read-path self-heal (Trending::current()), not on a schedule. Run bin/install.php as root to set it up.',
            null => 'Unknown - either systemctl isn\'t available on this host, or SELinux is denying the web server\'s own status query (run bin/install.php as root to fix that)',
        };

        $status_line = new Paragraph('Trending timer: ' . $status_text);

        if ($is_active === false) {
            $status_line -> class = 'Error';
        }

        $this -> addContent($status_line);

        return parent::toDOM();
    }
}
