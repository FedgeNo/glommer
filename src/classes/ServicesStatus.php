<?php

declare(strict_types=1);

/**
 * The admin Site Settings "Services" group: the upload worker, WebSocket
 * server, and trending timer health cards stacked together, each its own card,
 * so the background daemons read as one section instead of three.
 */
class ServicesStatus extends Div
{
    public ?string $class = 'ServicesStatus d-flex flex-column gap-2';

    public function toDOM(): \DOMElement
    {
        $this -> contents[] = new UploadWorkerStatus();
        $this -> contents[] = new WebSocketStatus();
        $this -> contents[] = new TrendingTimerStatus();

        return parent::toDOM();
    }
}
