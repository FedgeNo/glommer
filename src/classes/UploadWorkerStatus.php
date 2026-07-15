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
    public ?string $class = 'Card d-flex flex-column gap-2 UploadWorkerStatus';

    public function toDOM(): \DOMElement
    {
        $is_active = UploadBatch::workerIsActive();

        $status_text = match ($is_active) {
            true => 'Running',
            false => 'Not running - staged uploads will never be transcoded until it is restarted',
            null => 'Unknown - systemctl isn\'t available on this host',
        };

        $status_line = new Paragraph('Worker service: ' . $status_text);

        if ($is_active === false) {
            $status_line -> class = 'Error';
        }

        $this -> contents[] = $status_line;

        $depth = UploadBatch::queueDepth();

        $this -> contents[] = new Paragraph(sprintf(
            'Queue: %d staging, %d pending, %d processing',
            $depth['staging'],
            $depth['pending'],
            $depth['processing']
        ));

        return parent::toDOM();
    }
}
