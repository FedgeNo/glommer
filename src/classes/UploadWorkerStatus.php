<?php

declare(strict_types=1);

/**
 * Read-only upload-worker health for the admin Site Settings page: whether
 * the systemd service is currently running, and how many batches are sitting
 * in each stage of the disk-backed queue right now - lets the admin tell
 * "dead" from "alive but backlogged" at a glance instead of SSHing in.
 */
class UploadWorkerStatus extends Div
{
    public ?string $class = 'UploadWorkerStatus';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $words = Strings::for(self::class);
        $is_active = UploadBatch::workerIsActive();

        // One whole phrase per state rather than a label glued to a word: a
        // language that puts the verdict before the noun needs the sentence,
        // not the pieces.
        $status_key = match ($is_active) {
            true => 'running',
            false => 'stopped',
            null => 'unknown',
        };

        $status_line = new Paragraph((string) ($words[$status_key] ?? ''));

        if ($is_active === false) {
            $status_line -> class = 'Error';
        }

        $this -> contents[] = $status_line;

        $depth = UploadBatch::queueDepth();

        $this -> contents[] = new Paragraph(str_replace(
            ['{staging}', '{pending}', '{processing}'],
            [(string) $depth['staging'], (string) $depth['pending'], (string) $depth['processing']],
            (string) ($words['queue'] ?? '')
        ));

        return parent::toDOM();
    }
}
