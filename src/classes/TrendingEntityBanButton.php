<?php

declare(strict_types=1);

class TrendingEntityBanButton extends ButtonButton
{
    public function __construct(string $entity_type, string $entity_value)
    {
        parent::__construct();

        $this -> attributes['data-entity-type'] = $entity_type;
        $this -> attributes['data-entity-value'] = $entity_value;
        $this -> addContent('Ban');
    }
}
