<?php

declare(strict_types=1);

/**
 * Read-only trending-timer health for the Admin Settings page - whether
 * glommer-trending.timer is confirmed armed and waiting for its next run.
 * Mirrors UploadWorkerStatus's three-way active/not-running/unknown states
 * and the same SELinux status-query caveat.
 */
class TrendingTimerStatus extends Div
{
    public ?string $class = 'TrendingTimerStatus';

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $is_active = Trending::timerIsActive();

        // One whole phrase per state - see UploadWorkerStatus, which has the
        // same three states for the same reason.
        $status_key = match ($is_active) {
            true => 'running',
            false => 'stopped',
            null => 'unknown',
        };

        $status_line = new Paragraph((string) ($words[$status_key] ?? ''));

        if ($is_active === false) {
            $status_line -> class = 'Error';
        }

        $this -> addContent($status_line);

        return parent::toDOM();
    }
}
