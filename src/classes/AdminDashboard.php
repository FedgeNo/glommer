<?php

declare(strict_types=1);

/** The always-visible overview above the admin's settings and diagnostic panels. */
class AdminDashboard extends Section
{
    public ?string $class = 'AdminDashboard';
    public ?array $snapshot = null;

    public function toDOM(): \DOMElement
    {
        $snapshot = $this -> snapshot ?? AdminStatus::snapshot();
        $this -> addContent(new ServerHealth(['readings' => $snapshot['health']]));
        $this -> addContent(new StatusBoard(['tiles' => $snapshot['tiles']]));
        $status = new Paragraph('');
        $status -> attributes['data-refresh-status'] = '';
        $status -> attributes['role'] = 'status';
        $this -> addContent($status);

        return parent::toDOM();
    }
}
