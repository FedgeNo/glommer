<?php

declare(strict_types=1);

/**
 * The admin Site Settings "Services" group: the upload worker, WebSocket
 * server, and trending timer health cards stacked together, each its own card,
 * so the background daemons read as one section instead of three - followed by
 * how the site itself is doing, which is the other half of the same question.
 */
class ServicesStatus extends Div
{
    public ?string $class = 'ServicesStatus';
    public array $mixins = ['d-flex', 'flex-column', 'gap-2'];

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new UploadWorkerStatus();
        $this -> contents[] = new WebSocketStatus();
        $this -> contents[] = new TrendingTimerStatus();
        $this -> contents[] = new SiteCounters();

        return parent::toDOM();
    }
}
