<?php

declare(strict_types=1);

class ReportButton extends ButtonButton
{
    public function __construct(string $target_type, int $target_id)
    {
        parent::__construct();

        $this -> attributes['data-target-type'] = $target_type;
        $this -> attributes['data-target-id'] = (string) $target_id;
        $this -> contents[] = 'Report';
    }
}
