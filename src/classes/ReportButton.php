<?php

declare(strict_types=1);

class ReportButton extends ButtonButton
{
    public function __construct(string $target_type, int $target_id)
    {
        parent::__construct();

        $this -> attributes['data-target-type'] = $target_type;
        $this -> attributes['data-target-id'] = (string) $target_id;
        // self::class, not static::class: PostReportButton is the same button
        // pointed at a post, and says the same word.
        $this -> contents[] = (string) (Strings::for(self::class)['name'] ?? '');
    }
}
